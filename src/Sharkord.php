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
			echo "[DEBUG] Starting Bot...\n";
			$this->authenticate();
			$this->loop->run();
		}

		private function authenticate(): void {
			$authUrl = "https://{$this->config['host']}/login";
			echo "[DEBUG] Authenticating with: $authUrl\n";

			$this->browser->post(
				$authUrl, 
				['Content-Type' => 'application/json'], 
				json_encode([
					'identity' => $this->config['identity'],
					'password' => $this->config['password']
				])
			)->then(function (ResponseInterface $response) {
				$data = json_decode((string)$response->getBody(), true);
				if (isset($data['token'])) {
					echo "[DEBUG] Auth Success. Token acquired.\n";
					$this->token = $data['token'];
					$this->connectToWebSocket($this->token);
				} else {
					echo "[ERROR] Authentication failed: No token in response body.\n";
				}
			}, function (\Exception $e) {
				echo "[ERROR] Auth Request Failed: " . $e->getMessage() . "\n";
			});
		}

		private function connectToWebSocket(): void {

			$wsUrl = "wss://{$this->config['host']}/?connectionParams=1";

			echo "[DEBUG] Connecting to WebSocket: $wsUrl\n";

			$headers = [
				'Host' => $this->config['host'],
				'User-Agent' => 'Sharkord-Bot-v1'
			];

			($this->connector)($wsUrl, [], $headers)->then(
				function (WebSocket $conn) {
					echo "[DEBUG] WebSocket Pipe Open.\n";
					$this->conn = $conn;
					$this->setupInternalListeners($conn);
					$this->performHandshake($conn);
				},
				function (\Exception $e) {
					echo "[ERROR] WebSocket Connection Failed: " . $e->getMessage() . "\n";
				}
			);
		}

		private function performHandshake(WebSocket $conn): void {
			echo "[DEBUG] Step 1: Sending connectionParams...\n";
			$conn->send(json_encode([
				"jsonrpc" => "2.0",
				"method" => "connectionParams",
				"data" => [
					"token" => $this->token
				]
			]));
			
			\React\EventLoop\Loop::get()->addTimer(0.3, function() use ($conn) {
				echo "[DEBUG] Step 2: Sending Handshake Query (id: 1)\n";
				$conn->send(json_encode([
					"jsonrpc" => "2.0",
					"id" => 1,
					"method" => "query",
					"params" => ["path" => "others.handshake"]
				]));
			});
		}

		private function setupInternalListeners(WebSocket $conn): void {
			$conn->on('message', function ($payload) use ($conn) {
				$data = json_decode((string)$payload, true);

				// echo "[RAW] " . (string)$payload . "\n";

				if (isset($data['id']) && $data['id'] === 1) {
					$hash = $data['result']['data']['handshakeHash'] ?? null;
					if ($hash) {
						echo "[DEBUG] Handshake Success. Joining Server (id: 2)\n";
						$conn->send(json_encode([
							"jsonrpc" => "2.0",
							"id" => 2,
							"method" => "query",
							"params" => [
								"input" => ["handshakeHash" => $hash],
								"path" => "others.joinServer"
							]
						]));
					} else {
						echo "[ERROR] Handshake response missing hash.\n";
					}
					return;
				}

				if (isset($data['id']) && $data['id'] === 2) {
					
					$raw = $data['result']['data'];

					// Map Channels
					foreach ($raw['channels'] as $chan) {
						$this->channels[$chan['id']] = new Channel($chan['id'], $chan['name'], $chan['type'], $this);
					}

					// Map Users
					foreach ($raw['users'] as $u) {
						$this->users[$u['id']] = new User($u['id'], $u['name'], $u['status'], $u['roleIds']);
					}

					echo "[DEBUG] Server data cached: " . count($this->channels) . " channels ready.\n";				
					echo "[DEBUG] Join Server Success. Subscribing to messages.onNew (id: 3)\n";
					$conn->send(json_encode([
						"jsonrpc" => "2.0",
						"id" => 3,
						"method" => "subscription", 
						"params" => [
							"path" => "messages.onNew",
						]
					]));
					return;
					
				}

				if (isset($data['id']) && $data['id'] === 3 && $data['result']['type'] === 'started') {
					$this->emit('ready');
					return;
				}
				
				if (isset($data['id']) && $data['id'] === 3 && $data['result']['type'] === 'data') {
					$msgRaw = $data['result']['data'];

					$user = $this->users[$msgRaw['userId']] ?? new Models\User($msgRaw['userId'], 'Unknown', 'Unknown', []);
					$channel = $this->channels[$msgRaw['channelId']] ?? new Models\Channel($msgRaw['channelId'], 'Unknown', 'Unknown');
					
					$message = new Message(
						(int)$msgRaw['id'],
						strip_tags($msgRaw['content']),
						$user,
						$channel
					);

					$this->emit('message', [$message]);
					return;
				}

			});

			$conn->on('close', function (int $code, string $reason) {
				echo "[DEBUG] Connection closed ($code): $reason\n";
				$this->emit('close', [$code, $reason]);
			});
		}

		public function sendMessage(string $text, int|string $channelId): void {
			if (!$this->conn) return;

			$this->conn->send(json_encode([
				"jsonrpc" => "2.0",
				"id" => time(),
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
	}

?>