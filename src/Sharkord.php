<?php

	declare(strict_types=1);

	namespace Sharkord;

	use Evenement\EventEmitterTrait;
	use React\EventLoop\Loop;
	use React\EventLoop\LoopInterface;
	use React\Promise\PromiseInterface;
	use function React\Promise\reject;
	use function React\Promise\resolve;
	use Psr\Log\LoggerInterface;
	use Monolog\Logger;
	use Monolog\Handler\StreamHandler;
	use Monolog\Formatter\LineFormatter;
	use Monolog\Level;
	use Monolog\ErrorHandler;
	
	use Sharkord\HTTP\Client as Client;
	use Sharkord\WebSocket\Gateway;

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
		 * Sharkord constructor.
		 *
		 * @param array								$config       Configuration array containing 'host', 'identity', and 'password'.
		 * @param LoopInterface|null				$loop         The ReactPHP event loop instance.
		 * @param LoggerInterface|null				$logger       The PSR-3 logger instance.
		 * @param string							$logLevel     Default log level if instantiating Monolog.
		 */
		public function __construct(
			private array $config,
			private ?LoopInterface $loop = null,
			?LoggerInterface $logger = null,
			string $logLevel = 'Notice'
		) {

			$this->loop = $this->loop ?? Loop::get();
			
			// Restore default Monolog instantiation
			if ($logger === null) {
				
				$level = Level::fromName(ucfirst(strtolower($logLevel)));
				
				$outputFormat = null;
				$dateFormat = "d/m h:i:sA";
				
				$formatter = new LineFormatter($outputFormat, $dateFormat, false, true);
				$streamHandler = new StreamHandler('php://stdout', $level);
				$streamHandler->setFormatter($formatter);
				
				$logger = new Logger('sharkord');
				$logger->pushHandler($streamHandler);
				
				ErrorHandler::register($logger);
			}
			$this->logger = $logger;

			// Initialize Managers
			$this->channels = new ChannelManager($this);
			$this->users = new UserManager($this);
			$this->categories = new CategoryManager($this);
			$this->roles = new RoleManager($this);
			$this->servers = new ServerManager($this);
			$this->messages = new MessageManager($this);
			$this->commands = new CommandRouter($this);

			// Initialize Isolated Network Layers
			$this->http    = new Client($this->config, $this->loop, $this->logger);
			$this->gateway = new Gateway($this->config, $this->loop, $this->logger);

			// Bind Core Gateway Events
			$this->gateway->on('closed', function($code, $reason) {
				$this->logger->warning("Gateway connection lost. Code: {$code}. Reason: {$reason}");
			});

		}

		/**
		 * Starts the bot.
		 *
		 * Initiates authentication and starts the event loop.
		 *
		 * @return void
		 */
		public function run(): void {

			$this->logger->debug("Starting Bot...");

			$this->http->authenticate()
				->then(fn(string $token) => $this->gateway->connect($token))
				->then(fn(array $joinData) => $this->hydrateElements($joinData))
				->then(fn() => $this->setupSubscriptions())
				->then(function () {
					$this->logger->info("Bot is fully initialized and ready.");
					$this->emit('ready', [$this->bot]);
				})
				->catch(function (\Exception $e) {
					$this->logger->error("Fatal Startup Error: " . $e->getMessage());
				});

			$this->loop->run();

		}
		
		/**
		 * Gracefully shuts down the framework.
		 *
		 * @return void
		 */
		public function stop(): void {

			$this->logger->info("Shutting down...");
			$this->gateway->disconnect();
			$this->loop->stop();

		}

		/**
		 * Handles the response after joining the server.
		 *
		 * Hydrates the initial cache of users and channels and sets up subscriptions.
		 *
		 * @param array $data The JSON-decoded response data.
		 * @return PromiseInterface Resolves when hydration is complete.
		 */
		private function hydrateElements(array $data): PromiseInterface {

			$raw = $data['data'];

			// Hydrate Models efficiently
			foreach ($raw['roles'] ?? [] as $r) {
				$this->roles->hydrate($r);
			}
			foreach ($raw['categories'] ?? [] as $c) {
				$this->categories->hydrate($c);
			}
			foreach ($raw['channels'] as $c) {
				$this->channels->hydrate($c);
			}
			foreach ($raw['users'] as $u) {
				$this->users->hydrate($u);
			}
			
			$this->bot = $this->users->get($raw['ownUserId']);

			$this->servers->hydrate($raw['publicSettings']);

			$this->logger->info(sprintf("Connected! Cached %d channels, %d users.", $this->channels->count(), $this->users->count()));
			
			return resolve(null);
			
		}
		
		/**
		 * Registers all Gateway RPC subscriptions to keep the caches and events up to date.
		 *
		 * @return PromiseInterface Resolves when subscriptions are mapped.
		 */
		private function setupSubscriptions(): PromiseInterface {

			// Create server event subscriptions (Delegated to Gateway)
			$subscriptions = [
				'messages.onNew'    => fn($d) => $this->onNewMessage($d),
				
				'channels.onCreate' => fn($d) => $this->channels->create($d),
				'channels.onDelete' => fn($d) => $this->channels->delete($d),
				'channels.onUpdate' => fn($d) => $this->channels->update($d),
				
				'users.onCreate'    => fn($d) => $this->users->create($d),
				'users.onJoin'      => fn($d) => $this->users->join($d),
				'users.onLeave'     => fn($d) => $this->users->leave($d),
				'users.onUpdate'    => fn($d) => $this->users->update($d),
				'users.onDelete'    => fn($d) => $this->users->delete($d),
				
				'roles.onCreate'      => fn($d) => $this->roles->create($d),
				'roles.onUpdate'      => fn($d) => $this->roles->update($d),
				'roles.onDelete'      => fn($d) => $this->roles->delete($d),
				
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
		 * Handles a new message event.
		 *
		 * @param array $raw The raw message data.
		 * @return void
		 */
		private function onNewMessage(array $raw): void {

			$message = Message::fromArray($raw, $this);
			
			try {
				$this->emit('message', [$message]);
			} catch (\Throwable $e) {
				$errorMessage = "Uncaught Exception/Error in message processing: " . $e->getMessage();
				$errorMessage .= " on line " . $e->getLine() . " in " . $e->getFile();
				$this->logger->error($errorMessage);
			}

		}
		
	}

?>