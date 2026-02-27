<?php

	declare(strict_types=1);

	namespace Sharkord\HTTP;

	use React\EventLoop\LoopInterface;
	use React\Http\Browser;
	use React\Promise\PromiseInterface;
	use Psr\Http\Message\ResponseInterface;
	use Psr\Log\LoggerInterface;

	/**
	 * Class HTTPClient
	 *
	 * Responsible for handling HTTP communication with the Sharkord API,
	 * primarily the initial authentication to retrieve connection tokens.
	 *
	 * @package Sharkord\HTTP
	 */
	class HTTPClient {

		private Browser $browser;

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

	}

?>