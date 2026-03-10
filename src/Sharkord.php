<?php

	declare(strict_types=1);

	namespace Sharkord;

	use Evenement\EventEmitterTrait;
	use React\EventLoop\Loop;
	use React\EventLoop\LoopInterface;
	use React\Promise\PromiseInterface;
	use function React\Promise\resolve;
	use Psr\Log\LoggerInterface;
	use Monolog\Logger;
	use Monolog\Handler\StreamHandler;
	use Monolog\Formatter\LineFormatter;
	use Monolog\Level;
	use Monolog\ErrorHandler;
	
	use Sharkord\HTTP\Client;
	use Sharkord\WebSocket\Gateway;

	use Sharkord\Models\User;
	use Sharkord\Models\Message;
	use Sharkord\Managers\ChannelManager;
	use Sharkord\Managers\UserManager;
	use Sharkord\Managers\CategoryManager;
	use Sharkord\Managers\RoleManager;
	use Sharkord\Managers\ServerManager;
	use Sharkord\Managers\MessageManager;
	use Sharkord\Commands\CommandRouter;

	/**
	 * Class Sharkord
	 *
	 * The core orchestrator for the SharkordPHP framework.
	 * Manages the event loop, network layers, and data managers.
	 *
	 * @package Sharkord
	 */
	class Sharkord {
		
		use EventEmitterTrait;

		public readonly Client $http;
		public readonly Gateway $gateway;

		public ChannelManager $channels;
		public UserManager $users;
		public CategoryManager $categories;
		public RoleManager $roles;
		public LoggerInterface $logger;
		public ServerManager $servers;
		public MessageManager $messages;
		public CommandRouter $commands;
		
		/**
		 * The framework's own user object.
		 * @var User|null
		 */
		public ?User $bot = null;
		
		/**
		 * Tracks the number of reconnect attempts made since last successful connection.
		 * @var int
		 */
		private int $reconnectAttempts = 0;
		
		/**
		 * Guards against concurrent reconnect attempts being scheduled simultaneously.
		 * @var bool
		 */
		private bool $reconnecting = false;

		/**
		 * Sharkord constructor.
		 *
		 * @param array                $config               Configuration array containing 'host', 'identity', and 'password'.
		 * @param LoopInterface|null   $loop                 The ReactPHP event loop instance.
		 * @param LoggerInterface|null $logger               The PSR-3 logger instance.
		 * @param string               $logLevel             Default log level if instantiating Monolog.
		 * @param bool                 $reconnect            Whether to attempt reconnection on disconnect.
		 * @param int                  $maxReconnectAttempts Maximum number of reconnect attempts before exiting.
		 *
		 * @example
		 * ```php
		 * $sharkord = new Sharkord(
		 *     config: [
		 *         'identity' => $_ENV['CHAT_USERNAME'],
		 *         'password' => $_ENV['CHAT_PASSWORD'],
		 *         'host'     => $_ENV['CHAT_HOST'],
		 *     ],
		 *     logLevel:             'Notice',
		 *     reconnect:            true,
		 *     maxReconnectAttempts: 5
		 * );
		 * ```
		 */
		public function __construct(
			private array $config,
			private ?LoopInterface $loop = null,
			?LoggerInterface $logger = null,
			string $logLevel = 'Notice',
			private bool $reconnect = true,
			private int $maxReconnectAttempts = 5
		) {

			// Validate required config keys
			foreach (['host', 'identity', 'password'] as $key) {
				if (!isset($this->config[$key]) || $this->config[$key] === '') {
					throw new \InvalidArgumentException("Missing required config key: '{$key}'.");
				}
			}

			$this->loop = $this->loop ?? Loop::get();
			
			if ($logger === null) {
				
				try {
					$level = Level::fromName(ucfirst(strtolower($logLevel)));
				} catch (\ValueError $e) {
					throw new \InvalidArgumentException("Invalid log level '{$logLevel}': " . $e->getMessage(), 0, $e);
				}
				
				$outputFormat = null;
				$dateFormat   = "d/m h:i:sA";
				
				$formatter     = new LineFormatter($outputFormat, $dateFormat, false, true);
				$streamHandler = new StreamHandler('php://stdout', $level);
				$streamHandler->setFormatter($formatter);
				
				$logger = new Logger('sharkord');
				$logger->pushHandler($streamHandler);
				
				ErrorHandler::register($logger);

			}

			$this->logger = $logger;

			// Initialize Managers
			$this->channels   = new ChannelManager($this);
			$this->users      = new UserManager($this);
			$this->categories = new CategoryManager($this);
			$this->roles      = new RoleManager($this);
			$this->servers    = new ServerManager($this);
			$this->messages   = new MessageManager($this);
			$this->commands   = new CommandRouter($this);

			// Initialize Isolated Network Layers
			$this->http    = new Client($this->config, $this->loop, $this->logger);
			$this->gateway = new Gateway($this->config, $this->loop, $this->logger);

			// Bind Core Gateway Events
			$this->gateway->on('closed', function($code, $reason) {

				$this->logger->warning("Gateway connection lost. Code: {$code}. Reason: {$reason}");

				if (!$this->reconnect) {
					$this->loop->stop();
					return;
				}

				$this->loop->futureTick(function () {
					$this->attemptReconnect();
				});

			});

		}

		/**
		 * Starts the bot.
		 *
		 * Initiates authentication and starts the event loop.
		 *
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->run();
		 * ```
		 */
		public function run(): void {

			$this->logger->debug("Starting Bot...");

			$this->http->authenticate()
				->then(fn(string $token) => $this->gateway->connect($token))
				->then(fn(array $joinData) => $this->hydrateElements($joinData))
				->then(fn() => $this->setupSubscriptions())
				->then(function () {
					$this->reconnectAttempts = 0;
					$this->logger->info("Bot is fully initialized and ready.");
					$this->emit('ready', [$this->bot]);
				})
				->catch(function (\Exception $e) {
					$this->logger->error("Fatal Startup Error: " . $e->getMessage());
				});

			$this->loop->run();

		}
		
		/**
		 * Schedules a reconnection attempt using exponential backoff.
		 *
		 * Backs off at 2^attempt seconds (2s, 4s, 8s, 16s...) up to a cap of 60 seconds.
		 * Stops the event loop after maxReconnectAttempts is exceeded.
		 *
		 * @return void
		 */
		private function attemptReconnect(): void {

			if ($this->reconnecting) {
				$this->logger->debug("Reconnect already in progress, ignoring duplicate trigger.");
				return;
			}

			$this->reconnecting = true;
			$this->reconnectAttempts++;

			if ($this->reconnectAttempts > $this->maxReconnectAttempts) {
				$this->logger->error(
					"Reconnection failed after {$this->maxReconnectAttempts} attempts. Exiting."
				);
				$this->loop->stop();
				return;
			}

			$delay = min(2 ** $this->reconnectAttempts, 60);

			$this->logger->notice(
				"Reconnect attempt {$this->reconnectAttempts}/{$this->maxReconnectAttempts} in {$delay}s..."
			);

			$this->loop->addTimer($delay, function () {

				$this->http->authenticate()
					->then(fn(string $token) => $this->gateway->connect($token))
					->then(fn(array $joinData) => $this->hydrateElements($joinData))
					->then(fn() => $this->setupSubscriptions())
					->then(function () {
						$this->reconnecting      = false;
						$this->reconnectAttempts = 0;
						$this->logger->info("Reconnected successfully.");
						$this->emit('ready', [$this->bot]);
					})
					->catch(function (\Exception $e) {
						$this->logger->error("Reconnect attempt failed: " . $e->getMessage());
						$this->reconnecting = false;
						$this->attemptReconnect();
					});

			});

		}
		
		/**
		 * Gracefully shuts down the framework.
		 *
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on('message', function(Message $message) use ($sharkord) {
		 *     if ($message->content === '!shutdown') {
		 *         $sharkord->stop();
		 *     }
		 * });
		 * ```
		 */
		public function stop(): void {

			$this->logger->info("Shutting down...");
			$this->reconnect = false;
			$this->gateway->disconnect();
			$this->loop->stop();

		}

		/**
		 * Handles the response after joining the server.
		 *
		 * Hydrates the initial cache of users, channels, roles, categories, and servers,
		 * then resolves the bot's own User instance.
		 *
		 * @param array $data The JSON-decoded response data from others.joinServer.
		 * @return PromiseInterface Resolves when hydration is complete.
		 *
		 * @throws \RuntimeException If the join payload is malformed or the bot user cannot be resolved.
		 */
		private function hydrateElements(array $data): PromiseInterface {

			if (!isset($data['data']) || !is_array($data['data'])) {
				throw new \RuntimeException("Invalid join response: missing 'data' payload.");
			}

			$raw = $data['data'];

			foreach ($raw['roles'] ?? [] as $r) {
				$this->roles->hydrate($r);
			}
			foreach ($raw['categories'] ?? [] as $c) {
				$this->categories->hydrate($c);
			}
			foreach ($raw['channels'] ?? [] as $c) {
				$this->channels->hydrate($c);
			}
			foreach ($raw['users'] ?? [] as $u) {
				$this->users->hydrate($u);
			}
			
			if (!isset($raw['ownUserId'])) {
				throw new \RuntimeException("Invalid join response: missing 'ownUserId'.");
			}
			
			$this->bot = $this->users->get($raw['ownUserId']);

			if ($this->bot === null) {
				throw new \RuntimeException(sprintf(
					"Invalid join response: bot user with ID '%s' not found in hydrated users list.",
					(string) $raw['ownUserId']
				));
			}

			$this->servers->hydrate($raw['publicSettings'] ?? []);

			$this->logger->info(sprintf(
				"Connected! Cached %d channels, %d users.",
				$this->channels->count(),
				$this->users->count()
			));
			
			return resolve(null);
			
		}
		
		/**
		 * Registers all Gateway RPC subscriptions to keep caches and events up to date.
		 *
		 * Subscriptions cover the full lifecycle of messages, channels, users, roles,
		 * categories, and server settings.
		 *
		 * @return PromiseInterface Resolves when all subscriptions have been dispatched.
		 */
		private function setupSubscriptions(): PromiseInterface {

			$subscriptions = [
				'messages.onNew'    => fn($d) => $this->onNewMessage($d),
				'messages.onUpdate' => fn($d) => $this->onMessageUpdate($d),
				'messages.onDelete' => fn($d) => $this->onMessageDelete($d),
				
				'channels.onCreate' => fn($d) => $this->channels->create($d),
				'channels.onDelete' => fn($d) => $this->channels->delete($d),
				'channels.onUpdate' => fn($d) => $this->channels->update($d),
				
				'users.onCreate'    => fn($d) => $this->users->create($d),
				'users.onJoin'      => fn($d) => $this->users->join($d),
				'users.onLeave'     => fn($d) => $this->users->leave($d),
				'users.onUpdate'    => fn($d) => $this->users->update($d),
				'users.onDelete'    => fn($d) => $this->users->delete($d),
				
				'roles.onCreate'    => fn($d) => $this->roles->create($d),
				'roles.onUpdate'    => fn($d) => $this->roles->update($d),
				'roles.onDelete'    => fn($d) => $this->roles->delete($d),
				
				'categories.onCreate' => fn($d) => $this->categories->create($d),
				'categories.onUpdate' => fn($d) => $this->categories->update($d),
				'categories.onDelete' => fn($d) => $this->categories->delete($d),
				
				'others.onServerSettingsUpdate' => fn($d) => $this->servers->update($d),
			];

			foreach ($subscriptions as $path => $callback) {
			
				$this->gateway->subscribeRpc($path, function(mixed $eventData) use ($callback, $path) {
					try {
						$callback($eventData);
					} catch (\Exception $e) {
						$this->logger->error("Error processing event for {$path}: " . $e->getMessage());
					}
				});
				
				$this->logger->debug("Subscribing to event stream: {$path}");
				
			}

			return resolve(null);

		}

		/**
		 * Handles a new message event from the messages.onNew subscription.
		 *
		 * Constructs a Message model from the raw payload and emits the 'message' event.
		 *
		 * @param array $raw The raw message data from the server.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on('message', function(Message $message) {
		 *     echo $message->author->name . ': ' . $message->content;
		 * });
		 * ```
		 */
		private function onNewMessage(array $raw): void {

			$message = Message::fromArray($raw, $this);
			
			try {
				$this->emit('message', [$message]);
			} catch (\Throwable $e) {
				$this->logger->error(sprintf(
					"Uncaught Exception/Error in message processing: %s on line %d in %s",
					$e->getMessage(), $e->getLine(), $e->getFile()
				));
			}

		}
		
		/**
		 * Handles a message update event from the messages.onUpdate subscription.
		 *
		 * Fired when a message is edited, pinned, unpinned, or receives a reaction.
		 * Constructs a Message model from the raw payload and emits the 'messageupdate' event.
		 *
		 * @param array $raw The raw message data from the server.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on('messageupdate', function(Message $message) {
		 *     if ($message->isPinned()) {
		 *         echo "Message {$message->id} was just pinned.";
		 *     }
		 * });
		 * ```
		 */
		private function onMessageUpdate(array $raw): void {

			$message = Message::fromArray($raw, $this);

			try {
				$this->emit('messageupdate', [$message]);
			} catch (\Throwable $e) {
				$this->logger->error(sprintf(
					"Uncaught Exception/Error in messageupdate processing: %s on line %d in %s",
					$e->getMessage(), $e->getLine(), $e->getFile()
				));
			}

		}

		/**
		 * Handles a message delete event from the messages.onDelete subscription.
		 *
		 * Fired when any message is deleted by any user. Because a deleted message no
		 * longer exists on the server, only the identifying fields supplied by the API
		 * (at minimum 'id', typically also 'channelId') are available — a full Message
		 * model cannot be reconstructed. The raw payload array is emitted directly so
		 * listeners can act on the IDs they receive.
		 *
		 * @param array $raw The raw delete payload from the server. Expected to contain
		 *                   at least 'id' (the deleted message ID) and 'channelId'.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on('messagedelete', function(array $data) {
		 *     $messageId = $data['id'];
		 *     $channelId = $data['channelId'] ?? null;
		 *     echo "Message {$messageId} was deleted" . ($channelId ? " from channel {$channelId}" : '') . ".";
		 * });
		 * ```
		 */
		private function onMessageDelete(array $raw): void {

			if (!isset($raw['id'])) {
				$this->logger->warning("Received messages.onDelete event with no 'id' in payload.");
				return;
			}

			try {
				$this->emit('messagedelete', [$raw]);
			} catch (\Throwable $e) {
				$this->logger->error(sprintf(
					"Uncaught Exception/Error in messagedelete processing: %s on line %d in %s",
					$e->getMessage(), $e->getLine(), $e->getFile()
				));
			}

		}
		
	}

?>