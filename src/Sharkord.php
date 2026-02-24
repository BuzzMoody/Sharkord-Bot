<?php

	declare(strict_types=1);

	namespace Sharkord;

	use Evenement\EventEmitterTrait;
	use React\EventLoop\Loop;
	use React\EventLoop\LoopInterface;
	use React\Http\Browser;
	use React\Promise\Promise;
	use React\Promise\PromiseInterface;
	use function React\Promise\reject;
	use function React\Promise\resolve;
	use Ratchet\Client\Connector;
	use Ratchet\Client\WebSocket;
	use Psr\Http\Message\ResponseInterface;
	use Psr\Log\LoggerInterface;
	use Monolog\Logger;
	use Monolog\Handler\StreamHandler;
	use Monolog\Formatter\LineFormatter;
	use Monolog\Level;
	use Monolog\ErrorHandler;
	use LitEmoji\LitEmoji;
	use Sharkord\Models\User;
	use Sharkord\Models\Channel;
	use Sharkord\Models\Message;
	use Sharkord\Models\Server;
	use Sharkord\Commands\CommandInterface;
	use Sharkord\Managers\ChannelManager;
	use Sharkord\Managers\UserManager;
	use Sharkord\Managers\CategoryManager;
	use Sharkord\Managers\RoleManager;
	use Sharkord\Managers\ServerManager;
	use Sharkord\Permission;

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
		public CategoryManager $categories;
		public RoleManager $roles;
		public LoggerInterface $logger;
		public ServerManager $servers;
		
		/**
		 * The bot's own user object.
		 * @var User|null
		 */
		public ?User $bot = null;

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
			$this->categories = new CategoryManager($this);
			$this->roles = new RoleManager($this);
			$this->servers = new ServerManager($this);
			
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
					$conn->on('message', fn($msg) => $this->handleServerJSON((string)$msg));
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

			$this->sendRpc("query", ["path" => "others.handshake"])
				->then(
					function (array $result) {
						// Success! Pass the resolved result to the response handler
						$this->onHandshakeResponse($result);
					},
					function (\Exception $e) {
						// Handle any API errors or connection failures
						$this->logger->error("Handshake failed: " . $e->getMessage());
						$this->conn->close();
					}
				);

		}

		/**
		 * Handles the response from the handshake request.
		 *
		 * @param array $data The JSON-decoded response data.
		 * @return void
		 */
		private function onHandshakeResponse(array $data): void {

			$hash = $data['data']['handshakeHash'] ?? null;
			
			if (!$hash) {
				$this->logger->error("Missing handshake hash in the server response.");
				return;
			}

			$this->logger->info("Handshake OK. Joining Server...");

			$this->sendRpc("query", [
				"input" => ["handshakeHash" => $hash],
				"path" => "others.joinServer"
			])->then(
				function (array $joinResult) {
					// Trigger your new onJoinResponse method that sets up the subscriptions!
					$this->onJoinResponse($joinResult);
				},
				function (\Exception $e) {
					$this->logger->error("Failed to join the server: " . $e->getMessage());
				}
			);

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

			print_r($data);

			$raw = $data['data'];

			// Hydrate Models efficiently
			foreach ($raw['roles'] ?? [] as $r) {
				$this->roles->handleCreate($r);
			}
			foreach ($raw['categories'] ?? [] as $c) {
				$this->categories->handleCreate($c);
			}
			foreach ($raw['channels'] as $c) {
				$this->channels->handleCreate($c);
			}
			foreach ($raw['users'] as $u) {
				$this->users->handleCreate($u);
			}
			
			$this->bot = $this->users->get($raw['ownUserId']);

			$this->servers->handleCreate($raw['publicSettings']);

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
				'users.onUpdate'    => fn($d) => $this->users->handleUpdate($d),
				
				'roles.onCreate'      => fn($d) => $this->roles->handleCreate($d),
				'roles.onUpdate'      => fn($d) => $this->roles->handleUpdate($d),
				'roles.onDelete'      => fn($d) => $this->roles->handleDelete($d),
				
				'categories.onCreate' => fn($d) => $this->categories->handleCreate($d),
				'categories.onUpdate' => fn($d) => $this->categories->handleUpdate($d),
				'categories.onDelete' => fn($d) => $this->categories->handleDelete($d),
			];

			foreach ($subscriptions as $path => $callback) {
			
				// Use our new subscribeRpc method for persistent listening
				$this->subscribeRpc($path, function(array $eventData) use ($callback, $path) {
					
					try {
						// Execute your arrow function and pass the event data to it!
						$callback($eventData);
					} catch (\Exception $e) {
						$this->logger->error("Error processing event for {$path}: " . $e->getMessage());
					}
					
				});
				
				$this->logger->debug("Subscribed to event stream: {$path}");
				
			}

			$this->emit('ready', [$this->bot]);

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
				return; // Ignore malformed JSON
			}

			$this->logger->debug("Payload: $payload");
			
			$id = $data['id'] ?? null;

			if ($id && isset($this->rpcHandlers[$id])) {
				($this->rpcHandlers[$id])($data);
			}

		}

		/**
		 * Handles a new message event.
		 *
		 * @param array $raw The raw message data.
		 * @return void
		 */
		private function onNewMessage(array $raw): void {

			// Simply pass the raw payload and the bot instance to the model!
			$message = Message::fromArray($raw, $this);
			
			try {
				
				// Tell the rest of the bot that a message arrived
				$this->emit('message', [$message]);
				
			} catch (\Throwable $e) {
				
				// If ANYTHING goes wrong inside any plugin or command listening 
				// to this event, it will be caught right here!
				
				$errorMessage = "Uncaught Exception/Error in message processing: " . $e->getMessage();
				$errorMessage .= " on line " . $e->getLine() . " in " . $e->getFile();
				
				// Log the error so you can fix it
				$this->logger->error($errorMessage);
				
				// Optional: You could even make the bot reply in Sharkord saying "Oops, I hit a bug!"
				// $message->channel->sendMessage("Oops, I ran into an internal error!");
				
			}

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
					$command->handle($this, $message, $args, $matches);
					
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
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function sendMessage(string $text, int|string $channelId): PromiseInterface {

			if (!$this->conn) {
				return reject(new \RuntimeException("WebSocket connection is not established."));
			}

			return $this->sendRpc("mutation", [
				"input" => [
					"content" => "<p>".htmlspecialchars($text)."</p>", 
					"channelId" => $channelId, 
					"files" => []
				], 
				"path" => "messages.send"
			]);

		}

		/**
		 * Bans a user from the server.
		 *
		 * @param User   $user   The user to ban.
		 * @param string $reason The reason for the ban.
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function ban(User $user, string $reason = 'No reason given.'): PromiseInterface {
			
			if (!$this->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}
			
			if (!$this->bot->hasPermission(Permission::MANAGE_USERS)) {
				return reject(new \RuntimeException("Missing MANAGE_USERS permission to ban {$user->name}."));
			}
			
			if ($user->isOwner()) { 
				return reject(new \RuntimeException("Cannot ban {$user->name} as they are the server owner."));
			}
			
			// Simply return the Promise from sendRpc directly!
			return $this->sendRpc("mutation", [
				"input" => ["userId" => $user->id, "reason" => $reason], 
				"path" => "users.ban"
			]);
			
		}

		/**
		 * Unbans a user from the server.
		 *
		 * @param User $user The user to unban.
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function unban(User $user): PromiseInterface {
			
			if (!$this->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}
			
			if (!$this->bot->hasPermission(Permission::MANAGE_USERS)) {
				return reject(new \RuntimeException("Missing MANAGE_USERS permission to unban {$user->name}."));
			}

			return $this->sendRpc("mutation", [
				"input" => ["userId" => $user->id], 
				"path" => "users.unban"
			]);
			
		}
		
		/**
		 * Kicks a user from the server.
		 *
		 * @param User   $user   The user to kick.
		 * @param string $reason The reason for the kick.
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function kick(User $user, string $reason = 'No reason given.'): PromiseInterface {
			
			if (!$this->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}
			
			if (!$this->bot->hasPermission(Permission::MANAGE_USERS)) {
				return reject(new \RuntimeException("Missing MANAGE_USERS permission to kick {$user->name}."));
			}
			
			if ($user->isOwner()) { 
				return reject(new \RuntimeException("Cannot kick {$user->name} as they are the server owner."));
			}
			
			return $this->sendRpc("mutation", [
				"input" => ["userId" => $user->id, "reason" => $reason], 
				"path" => "users.kick"
			]);
			
		}
		
		/**
		 * Deletes a user from the server.
		 *
		 * @param User  $user The user to delete.
		 * @param bool  $wipe Whether to delete all associated user data (posts, files, emoji, etc.).
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function delete(User $user, bool $wipe = false): PromiseInterface {
			
			if (!$this->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}
			
			if (!$this->bot->hasPermission(Permission::MANAGE_USERS)) {
				return reject(new \RuntimeException("Missing MANAGE_USERS permission to delete {$user->name}."));
			}
			
			if ($user->isOwner()) { 
				return reject(new \RuntimeException("Cannot delete {$user->name} as they are the server owner."));
			}
			
			return $this->sendRpc("mutation", [
				"input" => ["userId" => $user->id, "wipe" => $wipe], 
				"path" => "users.delete"
			]);
			
		}
			
		/**
		 * Adds or toggles an emoji reaction on a specific message.
		 *
		 * @param Message $message The message entity to react to.
		 * @param string  $emoji   The emoji character(s) to use for the reaction.
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function react(Message $message, string $emoji): PromiseInterface {
			
			if (!$this->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}
			
			if (!$this->bot->hasPermission(Permission::REACT_TO_MESSAGES)) {
				return reject(new \RuntimeException("Missing REACT_TO_MESSAGES permission."));
			}
			
			if (!$this->isEmoji($emoji)) {
				return reject(new \InvalidArgumentException("Invalid emoji provided: '{$emoji}'"));
			}
			
			$emojiText = $this->emojiToText($emoji);
			
			return $this->sendRpc("mutation", [
				"input" => ["messageId" => $message->id, "emoji" => $emojiText], 
				"path" => "messages.toggleReaction"
			]);
			
		}
		
		/**
		 * Validates whether a given string is exactly one single emoji.
		 *
		 * @param string $emoji The string to validate.
		 * @return bool Returns true if the string is exactly one valid emoji sequence, false otherwise.
		 */
		private function isEmoji(string $emoji): bool {
			
			// Added \x{1F3FB}-\x{1F3FF} to the modifier brackets to support skin tones
			$pattern = '/^\p{Extended_Pictographic}[\x{FE0F}\p{M}\x{1F3FB}-\x{1F3FF}]*(?:\x{200D}\p{Extended_Pictographic}[\x{FE0F}\p{M}\x{1F3FB}-\x{1F3FF}]*)*$/u';
			
			return preg_match($pattern, $emoji) === 1;
			
		}
		
		/**
		 * Turns the visual emoji into the text name
		 *
		 * @param string $emoji The emoji to convert.
		 * @return string Returns the text string value of the emoji
		 */
		private function emojiToText(string $emoji): string {
			
			$unicodeName = LitEmoji::encodeShortcode($emoji);
			return str_replace(array(' ', ':'), array('_', ''), strtolower($unicodeName));
			
		}
		
		/**
		 * Sends a JSON-RPC request over the WebSocket and returns a Promise.
		 *
		 * @param string $method The RPC method type (e.g., 'query', 'mutation', 'subscription').
		 * @param array  $params The parameters for the RPC call.
		 * @return PromiseInterface Resolves with the response data, or rejects on error.
		 */
		private function sendRpc(string $method, array $params): PromiseInterface {

			return new Promise(function ($resolve, $reject) use ($method, $params) {
				
				$id = ++$this->rpcCounter;

				$this->rpcHandlers[$id] = function(array $response) use ($resolve, $reject, $id) {
					
					unset($this->rpcHandlers[$id]);

					if (isset($response['error'])) {
						
						// 1. Extract the base error message and code
						$message = $response['error']['message'] ?? 'Unknown API Error';
						$code = $response['error']['code'] ?? 0;
						
						// 2. Look for deeper details inside the 'data' object
						$extraDetails = '';
						if (isset($response['error']['data'])) {
							$dataCode = $response['error']['data']['code'] ?? 'UNKNOWN';
							$httpStatus = $response['error']['data']['httpStatus'] ?? 'N/A';
							$extraDetails = " (Status: {$httpStatus}, Type: {$dataCode})";
						}
						
						// 3. Reject with a beautifully detailed Exception
						$reject(new \RuntimeException("Sharkord API Error [{$code}]: {$message}{$extraDetails}"));
						
					} else {
						
						// Success! Resolve the promise with the result data
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
		private function subscribeRpc(string $path, callable $callback): void {
			
			$id = ++$this->rpcCounter;

			// Register a PERSISTENT handler
			$this->rpcHandlers[$id] = function(array $response) use ($callback, $path) {
				
				if (isset($response['error'])) {
					$message = $response['error']['message'] ?? 'Unknown API Error';
					$this->logger->error("Subscription Error [{$path}]: {$message}");
					return;
				}
				
				if (isset($response['result'])) {
					
					// tRPC uses 'type' to distinguish between setup confirmations and actual data
					$type = $response['result']['type'] ?? null;
					
					if ($type === 'started') {
						// The server is just confirming we are subscribed. Do NOT fire the callback!
						$this->logger->debug("Server confirmed subscription to: {$path}");
						return;
					}
					
					if ($type === 'stopped') {
						// The server has ended this subscription
						$this->logger->warning("Server stopped subscription to: {$path}");
						return;
					}
					
					if ($type === 'data') {
						// This is a REAL event! Pass the inner 'data' payload to your manager
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
	
	}
	
	
?>