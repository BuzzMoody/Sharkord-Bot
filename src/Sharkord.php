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
			private array $channels = [],
			private array $rpcHandlers = [],
			private int $rpcCounter = 0
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
				function (\Exception $e) { 
					echo "[ERROR] Auth Failed: " . $e->getMessage() . "\n"; 
				}
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
					$conn->on('message', fn($msg) => $this->handleMessage((string)$msg));
					$conn->on('close', fn($code, $reason) => $this->emit('close', [$code, $reason]));
					
					// Start protocol
					$this->performHandshake();
				},
				function (\Exception $e) {
					echo "[ERROR] WS Connection Failed: " . $e->getMessage() . "\n"; 
				}
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

			echo "[DEBUG] Sending Handshake Request...\n";
			
			$this->sendRpc(
				"query", 
				["path" => "others.handshake"], 
				fn($response) => $this->onHandshakeResponse($response) 
			);
		}
		
		private function onHandshakeResponse(array $data): void {
			$hash = $data['result']['data']['handshakeHash'] ?? null;
			if (!$hash) {
				echo "[ERROR] Missing handshake hash.\n";
				return;
			}

			echo "[DEBUG] Handshake OK. Joining Server...\n";
			
			$this->sendRpc("query", 
				[
					"input" => ["handshakeHash" => $hash],
					"path" => "others.joinServer"
				],
				fn($response) => $this->onJoinResponse($response)
			);
		}

		private function handleMessage(string $payload): void {
			try {
				$data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
			} catch (\JsonException) {
				return; // Ignore malformed JSON
			}
			
			$id = $data['id'] ?? null;
			
			if ($id && isset($this->rpcHandlers[$id])) {
				($this->rpcHandlers[$id])($data);
			}
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
			
			// Create server event subscriptions 
			$subscriptions = [
				'messages.onNew'	=> fn($d) => $this->onNewMessage($d),
				'channels.onCreate'	=> fn($d) => $this->onChannelCreate($d),
				'channels.onDelete'	=> fn($d) => $this->onChannelDelete($d),
				'channels.onUpdate'	=> fn($d) => $this->onChannelUpdate($d),
				'users.onCreate'	=> fn($d) => $this->onUserCreate($d),
				'users.onJoin'		=> fn($d) => $this->onUserJoin($d),
				'users.onLeave'		=> fn($d) => $this->onUserLeave($d),
				'users.onUpdate'	=> fn($d) => $this->onUserUpdate($d)
			];
			
			foreach ($subscriptions as $path => $handler) {
        
				$this->sendRpc('subscription', ['path' => $path], function(array $response) use ($path, $handler) {
					
					$type = $response['result']['type'] ?? '';

					if ($type === 'started') {
						echo "[DEBUG] Subscribed to $path successfully.\n";
					} 
					elseif ($type === 'data') {
						$handler($response['result']['data']);
					}
					
				});
			}

			$this->emit('ready');
		}

		private function onNewMessage(array $raw): void {
			$user = $this->users[$raw['userId']] ?? new User($raw['userId'], 'Unknown', 'offline', []);
			$channel = $this->channels[$raw['channelId']] ?? new Channel($raw['channelId'], 'Unknown', 'TEXT');

			$message = new Message(
				(int)$raw['id'],
				strip_tags($raw['content']),
				$user,
				$channel
			);

			$this->emit('message', [$message]);
		}
		
		private function onChannelCreate(array $raw): void {
			
			$this->channels[$raw['id']] = new Channel($raw['id'], $raw['name'], $raw['type'], $this);
			
		}
		
		private function onChannelDelete(array $raw): void {
			
			unset($this->channels[$raw['id']]);
			
		}
		
		private function onChannelUpdate(array $raw): void {
			
			if (!isset($this->channels[$raw['id']])) return;
			
			$this->channels[$raw['id']]->update($raw['name'], $raw['type']);
			
		}
		
		private function onUserCreate(array $raw): void {
			
			$this->users[$raw['id']] = new User($raw['id'], $raw['name'], 'offline', $raw['roleIds']);
			
		}
		
		private function onUserJoin(array $raw): void {
			
			if (!isset($this->users[$raw['id']])) return;
			
			$this->users[$raw['id']]->updateStatus('online');
			
		}
		
		private function onUserLeave(array $raw): void {
			
			if (!isset($this->users[$raw['id']])) return;
			
			$this->users[$raw['id']]->updateStatus('offline');
			
		}
		
		private function onUserUpdate(array $raw): void {
			
			if (!isset($this->users[$raw['id']])) return;
			
			$this->users[$raw['id']]->updateName($raw['name']);
			
		}

		public function sendMessage(string $text, int|string $channelId): void {
			
			if (!$this->conn) return;		
			
			$this->sendRpc("mutation", ["input" => ["content" => "<p>".htmlspecialchars($text)."</p>", "channelId" => $channelId, "files" => []], "path" => "messages.send"]);
			
		}

		private function sendRpc(string $method, array $params, ?callable $callback = null): void {
			$id = ++$this->rpcCounter;
			
			if ($callback) $this->rpcHandlers[$id] = $callback;
			
			$this->conn->send(json_encode([
				"jsonrpc" => "2.0",
				"id" => $id,
				"method" => $method,
				"params" => $params
			], JSON_THROW_ON_ERROR));
		}
	}
	
?>