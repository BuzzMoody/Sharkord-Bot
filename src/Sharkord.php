<?php

	declare(strict_types=1);

	namespace Sharkord;

	use Evenement\EventEmitterTrait;
	use React\EventLoop\Loop;
	use React\EventLoop\LoopInterface;
	use React\Http\Browser;
	use Ratchet\Client\Connector;
	use Ratchet\Client\WebSocket;
	use Psr\Http\Message\ResponseInterface;
	use Sharkord\Models\User;
	use Sharkord\Models\Channel;
	use Sharkord\Models\Message;

	class Sharkord {
		use EventEmitterTrait;

		// Define protocol steps as constants for clarity
		private const STEP_HANDSHAKE = 1;
		private const STEP_JOIN = 2;
		private const STEP_SUBSCRIBE = 3;

		public function __construct(
			private array $config,
			private ?LoopInterface $loop = null,
			private ?Browser $browser = null,
			private ?Connector $connector = null,
			private ?WebSocket $conn = null,
			private string $token = '',
			private array $users = [],
			private array $channels = []
		) {
			$this->loop = $this->loop ?? Loop::get();
			$this->browser = $this->browser ?? new Browser($this->loop);
			$this->connector = $this->connector ?? new Connector($this->loop);
		}

		public function run(): void {
			echo "[INFO] Starting Bot...\n";
			$this->authenticate();
			$this->loop->run();
		}

		private function authenticate(): void {
			$authUrl = "https://{$this->config['host']}/login";
			echo "[DEBUG] Authenticating...\n";

			$this->browser->post(
				$authUrl,
				['Content-Type' => 'application/json'],
				json_encode([
					'identity' => $this->config['identity'],
					'password' => $this->config['password']
				], JSON_THROW_ON_ERROR)
			)->then(
				function (ResponseInterface $response) {
					$data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
					$this->token = $data['token'] ?? throw new \RuntimeException("No token in response");
					
					echo "[DEBUG] Auth Success.\n";
					$this->connectToWebSocket();
				},
				fn (\Exception $e) => echo "[ERROR] Auth Failed: " . $e->getMessage() . "\n"
			);
		}

		private function connectToWebSocket(): void {
			$wsUrl = "wss://{$this->config['host']}/?connectionParams=1";
			$headers = ['Host' => $this->config['host'], 'User-Agent' => 'Sharkord-Bot-v1'];

			($this->connector)($wsUrl, [], $headers)->then(
				function (WebSocket $conn) {
					echo "[DEBUG] WebSocket Connected.\n";
					$this->conn = $conn;
					
					// Attach listeners
					$conn->on('message', fn($msg) => $this->handleMessage($msg));
					$conn->on('close', fn($code, $reason) => $this->emit('close', [$code, $reason]));
					
					// Start protocol
					$this->performHandshake();
				},
				fn (\Exception $e) => echo "[ERROR] WS Connection Failed: " . $e->getMessage() . "\n"
			);
		}

		private function performHandshake(): void {
			if (!$this->conn) return;

			// Send connection params
			$this->conn->send(json_encode([
				"jsonrpc" => "2.0",
				"method" => "connectionParams",
				"data" => ["token" => $this->token]
			]));

			// Immediately send handshake query (No timer needed)
			$this->sendRpc(self::STEP_HANDSHAKE, "query", ["path" => "others.handshake"]);
		}

		private function handleMessage(string $payload): void {
			try {
				$data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
			} catch (\JsonException) {
				return; // Ignore malformed JSON
			}

			// Route logic based on ID (Protocol Steps)
			match ($data['id'] ?? null) {
				self::STEP_HANDSHAKE => $this->onHandshakeResponse($data),
				self::STEP_JOIN      => $this->onJoinResponse($data),
				self::STEP_SUBSCRIBE => $this->onSubscriptionResponse($data),
				default              => null
			};
		}

		private function onHandshakeResponse(array $data): void {
			$hash = $data['result']['data']['handshakeHash'] ?? null;
			if (!$hash) {
				echo "[ERROR] Missing handshake hash.\n";
				return;
			}

			echo "[DEBUG] Handshake OK. Joining Server...\n";
			$this->sendRpc(self::STEP_JOIN, "query", [
				"input" => ["handshakeHash" => $hash],
				"path" => "others.joinServer"
			]);
		}

		private function onJoinResponse(array $data): void {
			$raw = $data['result']['data'];

			// Hydrate Models efficiently
			foreach ($raw['channels'] as $c) {
				$this->channels[$c['id']] = new Channel($c['id'], $c['name'], $c['type'], $this);
			}
			foreach ($raw['users'] as $u) {
				$this->users[$u['id']] = new User($u['id'], $u['name'], $u['status'], $u['roleIds']);
			}

			echo "[DEBUG] Joined. Cached " . count($this->channels) . " channels.\n";
			
			// Subscribe to messages
			$this->sendRpc(self::STEP_SUBSCRIBE, "subscription", ["path" => "messages.onNew"]);
		}

		private function onSubscriptionResponse(array $data): void {
			$type = $data['result']['type'] ?? '';

			if ($type === 'started') {
				$this->emit('ready');
			} elseif ($type === 'data') {
				$this->processIncomingMessage($data['result']['data']);
			}
		}

		private function processIncomingMessage(array $raw): void {
			// Fallback to "Unknown" objects if ID not found in cache
			$user = $this->users[$raw['userId']] ?? new User($raw['userId'], 'Unknown', 'Unknown', []);
			$channel = $this->channels[$raw['channelId']] ?? new Channel($raw['channelId'], 'Unknown', 'Unknown');

			$message = new Message(
				(int)$raw['id'],
				strip_tags($raw['content']),
				$user,
				$channel
			);

			$this->emit('message', [$message]);
		}

		public function sendMessage(string $text, int|string $channelId): void {
			if (!$this->conn) return;

			// Use current time + random hex for ID to avoid collisions
			$reqId = (int)(microtime(true) * 1000); 

			$this->conn->send(json_encode([
				"jsonrpc" => "2.0",
				"id" => $reqId, 
				"method" => "mutation",
				"params" => [
					"input" => [
						"content" => "<p>" . htmlspecialchars($text) . "</p>",
						"channelId" => $channelId,
						"files" => []
					],
					"path" => "messages.send"
				]
			]));
		}

		// Helper to reduce repetitive JSON construction
		private function sendRpc(int $id, string $method, array $params): void {
			$this->conn->send(json_encode([
				"jsonrpc" => "2.0",
				"id" => $id,
				"method" => $method,
				"params" => $params
			], JSON_THROW_ON_ERROR));
		}
	}
	
?>