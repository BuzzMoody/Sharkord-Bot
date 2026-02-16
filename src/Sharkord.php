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
	use Sharkord\Commands\CommandInterface;

	/**
	 * Class Sharkord
	 *
	 * The main bot class responsible for handling WebSocket connections,
	 * authentication, and event emission.
	 *
	 * @package Sharkord
	 */
	class Sharkord {
		
		use EventEmitterTrait;

		/**
		 * Sharkord constructor.
		 *
		 * @param array								$config       Configuration array containing 'host', 'identity', and 'password'.
		 * @param LoopInterface|null				$loop         The ReactPHP event loop instance.
		 * @param Browser|null						$browser      The ReactPHP Browser instance for HTTP requests.
		 * @param Connector|null					$connector    The Ratchet Connector for WebSocket connections.
		 * @param WebSocket|null					$conn         The active WebSocket connection instance.
		 * @param string							$token        The authentication token received after login.
		 * @param array<int, User>					$users        Cache of User models indexed by ID.
		 * @param array<int, Channel>				$channels     Cache of Channel models indexed by ID.
		 * @param array								$rpcHandlers  Callbacks for pending RPC requests.
		 * @param int								$rpcCounter   Counter for generating unique RPC IDs.
		 * @param array<string, CommandInterface>	$commands     Registry of available commands, indexed by command name.
		 */
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
			private int $rpcCounter = 0,
			private array $commands = []
		) {

			$this->loop = $this->loop ?? Loop::get();
			$this->browser = $this->browser ?? new Browser($this->loop);
			$this->connector = $this->connector ?? new Connector($this->loop);

		}

		/**
		 * Starts the bot.
		 *
		 * Initiates authentication and starts the event loop.
		 *
		 * @return void
		 */
		public function run(): void {

			echo "[INFO] Starting Bot...\n";
			$this->authenticate();
			$this->loop->run();

		}

		/**
		 * Authenticates with the server via HTTP to retrieve a token.
		 *
		 * @return void
		 */
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

		/**
		 * Connects to the WebSocket server using the retrieved token.
		 *
		 * @return void
		 */
		private function connectToWebSocket(): void {
			
			$wsUrl = "wss://{$this->config['host']}/?connectionParams=1";
			$headers = ['Host' => $this->config['host'], 'User-Agent' => 'Sharkord-Bot-v1'];

			($this->connector)($wsUrl, [], $headers)->then(
				function (WebSocket $conn) {
					echo "[DEBUG] WebSocket Connected.\n";
					$this->conn = $conn;

					// Attach listeners
					$conn->on('message', fn($msg) => $this->handleMessage((string)$msg));
					$conn->on('close', function($code, $reason) {
						echo "[WARN] Connection closed ({$code}). Reconnecting in 5s...\n";
						$this->loop->addTimer(5, fn() => $this->authenticate()); // Restart auth flow
					});

					// Start protocol
					$this->performHandshake();
				},
				function (\Exception $e) {
					echo "[ERROR] WS Connection Failed: " . $e->getMessage() . "\n";
					echo "[INFO] Retrying in 5s...\n";
					$this->loop->addTimer(5, fn() => $this->authenticate());
				}
			);

		}

		/**
		 * Performs the initial handshake to verify the connection.
		 *
		 * @return void
		 */
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

		/**
		 * Handles the response from the handshake request.
		 *
		 * @param array $data The JSON-decoded response data.
		 * @return void
		 */
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

		/**
		 * Processes incoming WebSocket messages.
		 *
		 * @param string $payload The raw message payload.
		 * @return void
		 */
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

		/**
		 * Handles the response after joining the server.
		 *
		 * Hydrates the initial cache of users and channels and sets up subscriptions.
		 *
		 * @param array $data The JSON-decoded response data.
		 * @return void
		 */
		private function onJoinResponse(array $data): void {

			$raw = $data['result']['data'];

			// Hydrate Models efficiently
			foreach ($raw['channels'] as $c) {
				$this->channels[$c['id']] = new Channel($c['id'], $c['name'], $c['type'], $this);
			}
			foreach ($raw['users'] as $u) {
				$this->users[$u['id']] = new User($u['id'], $u['name'], $u['status'], $u['roleIds']);
			}

			echo "[DEBUG] Joined. Cached ".count($this->channels)." channels.\n";

			// Create server event subscriptions
			$subscriptions = [
				'messages.onNew'    => fn($d) => $this->onNewMessage($d),
				'channels.onCreate' => fn($d) => $this->onChannelCreate($d),
				'channels.onDelete' => fn($d) => $this->onChannelDelete($d),
				'channels.onUpdate' => fn($d) => $this->onChannelUpdate($d),
				'users.onCreate'    => fn($d) => $this->onUserCreate($d),
				'users.onJoin'      => fn($d) => $this->onUserJoin($d),
				'users.onLeave'     => fn($d) => $this->onUserLeave($d),
				'users.onUpdate'    => fn($d) => $this->onUserUpdate($d)
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

		/**
		 * Handles a new message event.
		 *
		 * @param array $raw The raw message data.
		 * @return void
		 */
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

		/**
		 * Handles a channel creation event.
		 *
		 * @param array $raw The raw channel data.
		 * @return void
		 */
		private function onChannelCreate(array $raw): void {

			$this->channels[$raw['id']] = new Channel($raw['id'], $raw['name'], $raw['type'], $this);

		}

		/**
		 * Handles a channel deletion event.
		 *
		 * @param int $id The ID of the deleted channel.
		 * @return void
		 */
		private function onChannelDelete(int $id): void {

			unset($this->channels[$id]);

		}

		/**
		 * Handles a channel update event.
		 *
		 * @param array $raw The raw channel data.
		 * @return void
		 */
		private function onChannelUpdate(array $raw): void {

			if (!isset($this->channels[$raw['id']])) return;

			$this->channels[$raw['id']]->update($raw['name'], $raw['type']);

		}

		/**
		 * Handles a user creation event.
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		private function onUserCreate(array $raw): void {

			$this->users[$raw['id']] = new User($raw['id'], $raw['name'], 'offline', $raw['roleIds']);

		}

		/**
		 * Handles a user join event.
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		private function onUserJoin(array $raw): void {

			if (!isset($this->users[$raw['id']])) return;

			$this->users[$raw['id']]->updateStatus('online');

		}

		/**
		 * Handles a user leave event.
		 *
		 * @param int $id The ID of the user leaving.
		 * @return void
		 */
		private function onUserLeave(int $id): void {

			if (!isset($this->users[$id])) return;

			$this->users[$id]->updateStatus('offline');

		}

		/**
		 * Handles a user update event.
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		private function onUserUpdate(array $raw): void {

			if (!isset($this->users[$raw['id']])) return;

			$this->users[$raw['id']]->updateName($raw['name']);

		}
		
		/**
		 * Registers a command instance to the bot.
		 *
		 * @param CommandInterface $command The command object to register.
		 * @return void
		 */
		public function registerCommand(CommandInterface $command): void {
			
			$this->commands[$command->getName()] = $command;
			echo "[INFO] Registered command: !{$command->getName()} (Pattern: {$command->getPattern()})\n";
			
		}
		
		/**
		 * Automatically loads and registers all command classes from a specific directory.
		 *
		 * This method scans the directory for PHP files, instantiates the classes
		 * if they implement CommandInterface, and registers them.
		 *
		 * @param string $directory The absolute path to the directory containing command classes.
		 * @return void
		 */
		public function loadCommands(string $directory): void {
			
			foreach (glob($directory . '/*.php') as $file) {
				
				$className = 'Sharkord\\Commands\\' . basename($file, '.php');

				if (class_exists($className)) {
					$reflection = new \ReflectionClass($className);
					
					// Only instantiate if it implements the interface and is not abstract
					if ($reflection->implementsInterface(CommandInterface::class) && !$reflection->isAbstract()) {
						$this->registerCommand(new $className());
					}
				}
				
			}
			
		}
		
		/**
		 * Checks if a received message matches a command pattern and executes it.
		 *
		 * Iterates through all registered commands and tests their regex pattern
		 * against the message content. Stops at the first match.
		 *
		 * @param Message $message The received message object.
		 * @return void
		 */
		public function handleCommand(Message $message, array $matches): void {
			
			$commandName = strtolower($matches[1]);
			$args = $matches[2] ?? '';
			
			foreach ($this->commands as $command) {
				// Check if the message matches the command's regex pattern
				if (preg_match($command->getPattern(), $commandName, $matches)) {
					echo "[DEBUG] Matched command: {$command->getName()}\n";
					
					// Pass the matches array (capture groups) to the handler
					$command->handle($message, $args, $matches);
					
					// Stop checking other commands after the first match
					return;
				}
			}
		}

		/**
		 * Sends a message to a specific channel.
		 *
		 * @param string     $text      The message content.
		 * @param int|string $channelId The target channel ID.
		 * @return void
		 */
		public function sendMessage(string $text, int|string $channelId): void {

			if (!$this->conn) return;

			$this->sendRpc("mutation", ["input" => ["content" => "<p>".htmlspecialchars($text)."</p>", "channelId" => $channelId, "files" => []], "path" => "messages.send"]);

		}

		/**
		 * Sends a JSON-RPC request over the WebSocket.
		 *
		 * @param string        $method   The RPC method type (e.g., 'query', 'mutation', 'subscription').
		 * @param array         $params   The parameters for the RPC call.
		 * @param callable|null $callback Optional callback to execute when a response is received.
		 * @return void
		 */
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