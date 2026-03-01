<?php

	declare(strict_types=1);

	namespace Sharkord\WebSocket;

	use Evenement\EventEmitterTrait;
	use React\EventLoop\LoopInterface;
	use React\EventLoop\TimerInterface;
	use React\Promise\Promise;
	use React\Promise\PromiseInterface;
	use function React\Promise\reject;
	use Ratchet\Client\Connector;
	use Ratchet\Client\WebSocket;
	use Psr\Log\LoggerInterface;

	/**
	 * Class Gateway
	 *
	 * Responsible for managing the persistent WebSocket connection to the Sharkord API,
	 * handling the handshake, and routing JSON-RPC requests and subscriptions.
	 *
	 * @package Sharkord\WebSocket
	 */
	class Gateway {

		use EventEmitterTrait;

		private ?WebSocket $conn = null;
		private string $token = '';
		private array $rpcHandlers = [];
		private int $rpcCounter = 0;
		
		// --- Watchdog Properties ---
		private ?TimerInterface $watchdogTimer = null;
		private ?TimerInterface $probeTimer = null;
		private int $watchdogTimeout = 31; // 30s expected + 1s grace period
		private int $probeTimeout = 3; // How long we wait for a PONG reply

		/**
		 * Gateway constructor.
		 *
		 * @param array           $config    Configuration array containing 'host'.
		 * @param LoopInterface   $loop      The ReactPHP event loop instance.
		 * @param LoggerInterface $logger    The PSR-3 logger instance.
		 * @param Connector|null  $connector The Ratchet Connector for WebSocket connections.
		 */
		public function __construct(
			private array $config,
			private LoopInterface $loop,
			private LoggerInterface $logger,
			private ?Connector $connector = null
		) {

			$this->connector = $this->connector ?? new Connector($this->loop);

		}

		/**
		 * Connects to the WebSocket server using the provided token.
		 *
		 * @param string $token The authentication token.
		 * @return PromiseInterface Resolves when the handshake and join are complete.
		 */
		public function connect(string $token): PromiseInterface {
			
			$this->token = $token;
			$wsUrl = "wss://{$this->config['host']}/?connectionParams=1";
			$headers = ['Host' => $this->config['host'], 'User-Agent' => 'SharkordPHP (https://github.com/BuzzMoody/SharkordPHP)'];

			return new Promise(function ($resolve, $reject) use ($wsUrl, $headers) {
				
				($this->connector)($wsUrl, [], $headers)->then(
					function (WebSocket $conn) use ($resolve) {
						
						$this->logger->debug("WebSocket Connected.");
						$this->conn = $conn;
						
						// Start the initial watchdog countdown
						$this->resetWatchdog();

						// Attach listeners
						$conn->on('message', fn($msg) => $this->handleServerJSON((string)$msg));
						
						$conn->on('close', function($code, $reason) {
							$this->logger->warning("Connection closed ({$code}). Emitting disconnect event...");
							$this->conn = null;
							
							if ($this->watchdogTimer) {
								$this->loop->cancelTimer($this->watchdogTimer);
								$this->watchdogTimer = null;
							}

							if ($this->probeTimer) {
								$this->loop->cancelTimer($this->probeTimer);
								$this->probeTimer = null;
							}
							
							$this->emit('closed', [$code, $reason]);
						});

						// Start protocol and resolve the promise once we fully join
						$this->performHandshake($resolve);
						
					},
					function (\Exception $e) use ($reject) {
						
						$this->logger->error("WS Connection Failed: " . $e->getMessage());
						$reject($e);
						
					}
				);
				
			});

		}
		
		/**
		 * Resets the watchdog timers. If the idle timeout is reached, it probes
		 * the server. If the probe timeout is reached, it disconnects.
		 */
		private function resetWatchdog(): void {
			
			// 1. Cancel any existing idle timer
			if ($this->watchdogTimer) {
				$this->loop->cancelTimer($this->watchdogTimer);
			}
			
			// 2. Cancel any active probe timer (since we just got activity)
			if ($this->probeTimer) {
				$this->loop->cancelTimer($this->probeTimer);
				$this->probeTimer = null;
			}

			// 3. Start the standard idle watchdog
			$this->watchdogTimer = $this->loop->addTimer($this->watchdogTimeout, function () {
				
				$this->logger->warning("Watchdog: No activity in {$this->watchdogTimeout}s. Probing server with PING...");
				
				// Send our probe
				if ($this->conn) {
					$this->conn->send('PING');
				}

				// Start the strict {$this->probeTimeout}-second countdown for the PONG reply
				$this->probeTimer = $this->loop->addTimer($this->probeTimeout, function () {
					$this->logger->error("Watchdog: Server did not reply to probe within {$this->probeTimeout}s. Disconnecting...");
					$this->disconnect();
				});
				
			});

		}

		/**
		 * Performs the initial handshake to verify the connection.
		 *
		 * @param callable $resolve The promise resolution callback from connect().
		 * @return void
		 */
		private function performHandshake(callable $resolve): void {

			if (!$this->conn) return;

			// Send connection params
			$this->conn->send(json_encode([
				"jsonrpc" => "2.0",
				"method" => "connectionParams",
				"data" => ["token" => $this->token]
			]));

			$this->logger->debug("Sending Handshake Request...");

			$this->sendRpc("query", ["path" => "others.handshake"])
				->then(
					function (array $result) use ($resolve) {
						$this->onHandshakeResponse($result, $resolve);
					},
					function (\Exception $e) {
						$this->logger->error("Handshake failed: " . $e->getMessage());
						if ($this->conn) $this->conn->close();
					}
				);

		}

		/**
		 * Handles the response from the handshake request and joins the server.
		 *
		 * @param array    $data    The JSON-decoded response data.
		 * @param callable $resolve The promise resolution callback.
		 * @return void
		 */
		private function onHandshakeResponse(array $data, callable $resolve): void {

			$hash = $data['data']['handshakeHash'] ?? null;
			
			if (!$hash) {
				$this->logger->error("Missing handshake hash in the server response.");
				if ($this->conn) {
					$this->conn->close();
				}
				return;
			}

			$this->logger->debug("Handshake OK. Joining Server...");

			$this->sendRpc("query", [
				"input" => ["handshakeHash" => $hash],
				"path" => "others.joinServer"
			])->then(
				function (array $joinResult) use ($resolve) {
					// We successfully joined! Resolve the initial connection promise
					// and pass the server data back to the Orchestrator for hydration.
					$resolve($joinResult);
				},
				function (\Exception $e) {
					$this->logger->error("Failed to join the server: " . $e->getMessage());
				}
			);

		}

		/**
		 * Processes incoming WebSocket messages.
		 *
		 * @param string $payload The raw message payload.
		 * @return void
		 */
		private function handleServerJSON(string $payload): void {

			try {
				$data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
			} catch (\JsonException) {
				$pingCheck = trim($payload);
				if ($pingCheck === 'PING' || $pingCheck === 'PONG') {
					$this->logger->debug("Received {$pingCheck} heartbeat from server.");
					if ($pingCheck === 'PING' && $this->conn) {
						$this->conn->send('PONG');
						$this->resetWatchdog();
					}
					else if ($pingCheck === 'PONG') {
						$this->resetWatchdog();
					}
				}
				return; // Ignore malformed JSON
			}

			$this->logger->debug("Payload: $payload");
			
			$id = $data['id'] ?? null;

			// If this matches an RPC ID we are tracking, pass it to the handler
			if ($id && isset($this->rpcHandlers[$id])) {
				($this->rpcHandlers[$id])($data);
			}

		}

		/**
		 * Sends a JSON-RPC request over the WebSocket and returns a Promise.
		 *
		 * @param string $method The RPC method type (e.g., 'query', 'mutation', 'subscription').
		 * @param array  $params The parameters for the RPC call.
		 * @return PromiseInterface Resolves with the response data, or rejects on error.
		 */
		public function sendRpc(string $method, array $params): PromiseInterface {

			if (!$this->conn) {
				return reject(new \RuntimeException("WebSocket connection is not established."));
			}

			return new Promise(function ($resolve, $reject) use ($method, $params) {
				
				$id = ++$this->rpcCounter;

				$this->rpcHandlers[$id] = function(array $response) use ($resolve, $reject, $id) {
					
					unset($this->rpcHandlers[$id]);
					
					$this->resetWatchdog();

					if (isset($response['error'])) {
						
						$message = $response['error']['message'] ?? 'Unknown API Error';
						$code = $response['error']['code'] ?? 0;
						
						$extraDetails = '';
						if (isset($response['error']['data'])) {
							$dataCode = $response['error']['data']['code'] ?? 'UNKNOWN';
							$httpStatus = $response['error']['data']['httpStatus'] ?? 'N/A';
							$extraDetails = " (Status: {$httpStatus}, Type: {$dataCode})";
						}
						
						$reject(new \RuntimeException("Sharkord API Error [{$code}]: {$message}{$extraDetails}"));
						
					} else {
						
						$resolve($response['result'] ?? []);
						
					}
					
				};

				$this->conn->send(json_encode([
					"jsonrpc" => "2.0",
					"id" => $id,
					"method" => $method,
					"params" => $params
				], JSON_THROW_ON_ERROR));
				
			});

		}

		/**
		 * Sends a JSON-RPC subscription request and registers a persistent callback.
		 *
		 * @param string   $path     The subscription path (e.g., 'messages.create').
		 * @param callable $callback The function to trigger when an event arrives.
		 * @return void
		 */
		public function subscribeRpc(string $path, callable $callback): void {
			
			if (!$this->conn) {
				throw new \RuntimeException("Cannot subscribe to RPC: WebSocket is not connected.");
			}

			$id = ++$this->rpcCounter;

			// Register a PERSISTENT handler (we do NOT unset this one)
			$this->rpcHandlers[$id] = function(array $response) use ($callback, $path) {
				
				if (isset($response['error'])) {
					$message = $response['error']['message'] ?? 'Unknown API Error';
					$this->logger->error("Subscription Error [{$path}]: {$message}");
					return;
				}
				
				if (isset($response['result'])) {
					
					$type = $response['result']['type'] ?? null;
					
					if ($type === 'started') {
						$this->logger->debug("Server confirmed subscription to: {$path}");
						$this->resetWatchdog();
						return;
					}
					
					if ($type === 'stopped') {
						$this->logger->warning("Server stopped subscription to: {$path}");
						return;
					}
					
					if ($type === 'data') {
						// Pass the inner 'data' payload to the provided callback
						$eventData = $response['result']['data'] ?? [];
						$callback($eventData);
					}
					
				}
				
			};

			// Send the subscription payload
			$this->conn->send(json_encode([
				"jsonrpc" => "2.0",
				"id" => $id,
				"method" => "subscription",
				"params" => [
					"path" => $path,
					"input" => []
				]
			], JSON_THROW_ON_ERROR));
			
		}
		
		/**
		 * Safely closes the current WebSocket connection.
		 * @return void
		 */
		public function disconnect(): void {
			
			if ($this->watchdogTimer) {
				$this->loop->cancelTimer($this->watchdogTimer);
				$this->watchdogTimer = null;
			}
			
			if ($this->probeTimer) {
				$this->loop->cancelTimer($this->probeTimer);
				$this->probeTimer = null;
			}

			if ($this->conn) {
				$this->logger->debug("Disconnecting from WebSocket...");
				$this->conn->close();
				$this->conn = null;
			}
			
		}

	}

?>