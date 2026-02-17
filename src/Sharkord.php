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
	use Psr\Log\LoggerInterface;
	use Monolog\Logger;
	use Monolog\Handler\StreamHandler;
	use Monolog\Formatter\LineFormatter;
	use Monolog\Level;
	use Monolog\ErrorHandler;
	use Sharkord\Models\User;
	use Sharkord\Models\Channel;
	use Sharkord\Models\Message;
	use Sharkord\Commands\CommandInterface;
	use Sharkord\Managers\ChannelManager;
	use Sharkord\Managers\UserManager;

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
		
		public ChannelManager $channels;
		public UserManager $users;
		public LoggerInterface $logger;

		/**
		 * Sharkord constructor.
		 *
		 * @param array								$config       Configuration array containing 'host', 'identity', and 'password'.
		 * @param LoopInterface|null				$loop         The ReactPHP event loop instance.
		 * @param Browser|null						$browser      The ReactPHP Browser instance for HTTP requests.
		 * @param Connector|null					$connector    The Ratchet Connector for WebSocket connections.
		 * @param WebSocket|null					$conn         The active WebSocket connection instance.
		 * @param string							$token        The authentication token received after login.
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
			private array $rpcHandlers = [],
			private int $rpcCounter = 0,
			private array $commands = [],
			?LoggerInterface $logger = null,
			string $logLevel = 'Notice'
		) {

			$this->loop = $this->loop ?? Loop::get();
			$this->browser = $this->browser ?? new Browser($this->loop);
			$this->connector = $this->connector ?? new Connector($this->loop);
			
			if ($logger === null) {
				
				$level = Level::fromName(ucfirst(strtolower($logLevel)));
				
				$outputFormat = null;
				$dateFormat = "d/m h:i:sA";
				
				$formatter = new LineFormatter($outputFormat, $dateFormat, false, true);
				$streamHandler = new StreamHandler('php://stdout', $logLevel);
				$streamHandler->setFormatter($formatter);
				
				$logger = new Logger('sharkord');
				$logger->pushHandler($streamHandler);
				
				// Optional: Register as global error handler
				ErrorHandler::register($logger);
			}
			$this->logger = $logger;
			
			$this->channels = new ChannelManager($this);
			$this->users = new UserManager($this);
			
		}

		/**
		 * Starts the bot.
		 *
		 * Initiates authentication and starts the event loop.
		 *
		 * @return void
		 */
		public function run(): void {

			$this->logger->info("Starting Bot...");
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
			$this->logger->info("Authenticating...");

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

					$this->logger->info("Auth Success.");
					$this->connectToWebSocket();
				},
				function (\Exception $e) {
					$this->logger->error("Auth Failed: " . $e->getMessage());
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
			$headers = ['Host' => $this->config['host'], 'User-Agent' => 'Sharkord ReactPHP Bot (https://github.com/BuzzMoody/Sharkord-Bot)'];

			($this->connector)($wsUrl, [], $headers)->then(
				function (WebSocket $conn) {
					$this->logger->info("WebSocket Connected.");
					$this->conn = $conn;

					// Attach listeners
					$conn->on('message', fn($msg) => $this->handleMessage((string)$msg));
					$conn->on('close', function($code, $reason) {
						$this->logger->warning("Connection closed ({$code}). Reconnecting in 5s...");
						$this->loop->addTimer(5, fn() => $this->authenticate()); // Restart auth flow
					});

					// Start protocol
					$this->performHandshake();
				},
				function (\Exception $e) {
					$this->logger->error("WS Connection Failed: " . $e->getMessage());
					$this->logger->info("Retrying in 5s...");
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

			$this->logger->info("Sending Handshake Request...");

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
				$this->logger->error("Missing handshake hash.");
				return;
			}

			$this->logger->info("Handshake OK. Joining Server...");

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

			$this->logger->debug("Payload: $payload");
			
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
				$this->channels->handleCreate($c);
			}
			foreach ($raw['users'] as $u) {
				$this->users->handleCreate($u);
			}

			$this->logger->info(sprintf("Joined. Cached %d channels, %d users.", $this->channels->count(), $this->users->count()));

			// Create server event subscriptions
			$subscriptions = [
				'messages.onNew'    => fn($d) => $this->onNewMessage($d),
				'channels.onCreate' => fn($d) => $this->channels->handleCreate($d),
				'channels.onDelete' => fn($d) => $this->channels->handleDelete($d),
				'channels.onUpdate' => fn($d) => $this->channels->handleUpdate($d),
				'users.onCreate'    => fn($d) => $this->users->handleCreate($d),
				'users.onJoin'      => fn($d) => $this->users->handleJoin($d),
				'users.onLeave'     => fn($d) => $this->users->handleLeave($d),
				'users.onUpdate'    => fn($d) => $this->users->handleUpdate($d)
			];

			foreach ($subscriptions as $path => $handler) {

				$this->sendRpc('subscription', ['path' => $path], function(array $response) use ($path, $handler) {

					$type = $response['result']['type'] ?? '';

					if ($type === 'started') {
						$this->logger->info("Subscribed to $path");
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

			$user = $this->users->get($raw['userId']) ?? new User($raw['userId'], 'Unknown', 'offline', []);
			$channel = $this->channels->get($raw['channelId']) ?? new Channel($raw['channelId'], 'Unknown', 'TEXT', []);

			$message = new Message(
				(int)$raw['id'],
				strip_tags($raw['content']),
				$user,
				$channel
			);

			$this->emit('message', [$message]);

		}
		
		/**
		 * Registers a command instance to the bot.
		 *
		 * @param CommandInterface $command The command object to register.
		 * @return void
		 */
		public function registerCommand(CommandInterface $command): void {
			
			$this->commands[$command->getName()] = $command;
			$this->logger->info("Registered command: " . $command->getName());
			
		}
		
		/**
		 * Automatically loads and registers all command classes from a specific directory.
		 *
		 * @param string $directory The absolute path to the directory containing command classes.
		 * @param string $namespace (Optional) The namespace used in the command files. Default is empty (global).
		 * @return void
		 */
		public function loadCommands(string $directory, string $namespace = ''): void {
			
			$namespace = rtrim($namespace, '\\');

			foreach (glob($directory . '/*.php') as $file) {
				
				require_once $file;
				$className = basename($file, '.php');
				$fullClassName = $namespace ? $namespace . '\\' . $className : $className;

				if (class_exists($fullClassName)) {
					$reflection = new \ReflectionClass($fullClassName);
					
					if ($reflection->implementsInterface(CommandInterface::class) && !$reflection->isAbstract()) {
						$this->registerCommand(new $fullClassName());
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
					$this->logger->info("Matched command: $commandName");
					
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