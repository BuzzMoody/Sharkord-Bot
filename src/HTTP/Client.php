<?php

	declare(strict_types=1);

	namespace Sharkord\HTTP;

	use React\EventLoop\LoopInterface;
	use React\Http\Browser;
	use React\Promise\PromiseInterface;
	use Psr\Http\Message\ResponseInterface;
	use Psr\Log\LoggerInterface;
	use function React\Promise\reject;

	/**
	 * Class Client
	 *
	 * Responsible for handling HTTP communication with the Sharkord API,
	 * including the initial authentication to retrieve connection tokens
	 * and binary file uploads to the storage endpoint.
	 *
	 * @package Sharkord\HTTP
	 */
	class Client {

		private Browser $browser;

		/**
		 * The authenticated JWT, stored after a successful authenticate() call.
		 * Required by upload() to set the x-token request header.
		 */
		private ?string $token = null;

		/**
		 * Client constructor.
		 *
		 * @param array           $config Configuration array containing 'host', 'identity', and 'password'.
		 * @param LoopInterface   $loop   The ReactPHP event loop instance.
		 * @param LoggerInterface $logger The PSR-3 logger instance.
		 */
		public function __construct(
			private array $config,
			private LoopInterface $loop,
			private LoggerInterface $logger
		) {

			$this->browser = new Browser($this->loop);

		}

		/**
		 * Authenticates with the server via HTTP to retrieve a WebSocket token.
		 *
		 * The token is cached internally so that subsequent upload() calls can
		 * attach it automatically via the x-token header.
		 *
		 * @return PromiseInterface Resolves with the authentication token (string), or rejects on failure.
		 */
		public function authenticate(): PromiseInterface {

			$authUrl = "https://{$this->config['host']}/login";
			$this->logger->debug("Authenticating via HTTP...");

			return $this->browser->post(
				$authUrl,
				['Content-Type' => 'application/json'],
				json_encode([
					'identity' => $this->config['identity'],
					'password' => $this->config['password']
				], JSON_THROW_ON_ERROR)
			)->then(
				function (ResponseInterface $response) {
					
					$data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
					$token = $data['token'] ?? throw new \RuntimeException("No token in response");

					$this->token = $token;
					$this->logger->debug("HTTP Auth Success. Token retrieved.");
					
					// Return the token so the next link in the Promise chain can use it
					return $token;
					
				},
				function (\Exception $e) {
					
					$this->logger->error("HTTP Auth Failed: " . $e->getMessage());
					
					// Re-throw the exception so the caller's catch() block is triggered
					throw $e;
					
				}
			);

		}

		/**
		 * Uploads raw binary content to the Sharkord storage endpoint.
		 *
		 * The server expects the file bytes as a raw `application/octet-stream`
		 * body with the original filename and MIME type supplied as custom headers.
		 * On success the server returns JSON containing an `id` field with the
		 * newly assigned file UUID, which is referenced in the `files` array of a
		 * subsequent `messages.send` RPC call.
		 *
		 * In most cases you should use {@see \Sharkord\Builders\MessageBuilder}
		 * via {@see \Sharkord\Models\Channel::sendMessage()} rather than calling
		 * this method directly. This method is intended for cases where you need
		 * the raw UUID before constructing a message, or for upload-only workflows
		 * that do not involve sending a message.
		 *
		 * authenticate() must have been called before invoking this method.
		 * Callers are responsible for reading file contents prior to this call —
		 * this method does not perform any filesystem I/O.
		 *
		 * @param string $contents Raw file bytes to upload.
		 * @param string $fileName The original filename sent in the x-file-name header (e.g. 'photo.jpg').
		 * @param string $mimeType The MIME type sent in the x-file-type header (e.g. 'image/jpeg').
		 * @return PromiseInterface Resolves with the uploaded file's UUID string, or rejects on failure.
		 *
		 * @example
		 * ```php
		 * // Preferred: use MessageBuilder to attach files to a message.
		 * $builder = \Sharkord\Builders\MessageBuilder::create()
		 *     ->setContent('Here is your file!')
		 *     ->addFile('/path/to/photo.jpg');
		 *
		 * $sharkord->channels->get('media')->sendMessage($builder);
		 * ```
		 *
		 * @example
		 * ```php
		 * // Direct upload when you need the UUID itself.
		 * $contents = file_get_contents('/path/to/photo.jpg');
		 * $sharkord->http->upload($contents, 'photo.jpg', 'image/jpeg')
		 *     ->then(function(string $fileId) {
		 *         echo "Uploaded. File ID: {$fileId}\n";
		 *     })
		 *     ->catch(function(\Throwable $e) {
		 *         echo "Upload failed: {$e->getMessage()}\n";
		 *     });
		 * ```
		 */
		public function upload(string $contents, string $fileName, string $mimeType): PromiseInterface {

			if ($this->token === null) {
				return reject(new \RuntimeException("Cannot upload: client has not authenticated yet."));
			}

			$safeFileName = $this->sanitizeHeaderValue($fileName);
			$safeMimeType = $this->sanitizeHeaderValue($mimeType);

			$uploadUrl = "https://{$this->config['host']}/upload";
			$this->logger->debug("Uploading '{$safeFileName}' ({$safeMimeType}, " . strlen($contents) . " bytes)...");

			return $this->browser->post(
				$uploadUrl,
				[
					'Content-Type' => 'application/octet-stream',
					'x-file-name'  => $safeFileName,
					'x-file-type'  => $safeMimeType,
					'x-token'      => $this->token,
				],
				$contents
			)->then(
				function (ResponseInterface $response): string {

					$status = $response->getStatusCode();
					$body   = (string) $response->getBody();

					if ($status < 200 || $status >= 300) {
						throw new \RuntimeException(
							"Upload failed with HTTP {$status}. Body: {$body}"
						);
					}

					// Primary: JSON response with an 'id' field
					try {
						$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
						if (is_array($data) && isset($data['id']) && is_string($data['id'])) {
							$this->logger->debug("Upload successful. File ID: {$data['id']}");
							return $data['id'];
						}
					} catch (\JsonException) {
						// Body is not JSON — fall through to plain-string check
					}

					// Fallback: server returned the UUID as a plain string body
					$uuid = trim($body);
					if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
						$this->logger->debug("Upload successful (plain body). File ID: {$uuid}");
						return $uuid;
					}

					throw new \RuntimeException(
						"Upload response did not contain a recognisable file ID. Body: {$body}"
					);

				},
				function (\Throwable $e): never {

					$this->logger->error("File upload failed: " . $e->getMessage());
					throw $e;

				}
			);

		}

		/**
		 * Strips control characters from a header value to prevent header injection.
		 *
		 * Removes all ASCII control characters (0x00–0x1F and 0x7F), which includes
		 * CR (\r), LF (\n), and NUL. This prevents header injection via crafted
		 * filenames or MIME types while preserving valid UTF-8 characters.
		 *
		 * @param string $value The raw header value.
		 * @return string The sanitized header value.
		 */
		private function sanitizeHeaderValue(string $value): string {

			return preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? $value;

		}

	}

?>