<?php

	declare(strict_types=1);

	namespace Sharkord;

	use Evenement\EventEmitterTrait;
	use Psr\Log\LoggerInterface;
	use React\EventLoop\Loop;
	use React\EventLoop\LoopInterface;

	use Sharkord\HTTP\Client;
	use Sharkord\Internal\ConnectionSession;
	use Sharkord\Internal\LoggerFactory;
	use Sharkord\Internal\ReconnectHandler;
	use Sharkord\Internal\PromiseUtils;
	use Sharkord\Internal\Guard;
	use Sharkord\Managers\CategoryManager;
	use Sharkord\Managers\ChannelManager;
	use Sharkord\Managers\MessageManager;
	use Sharkord\Managers\RoleManager;
	use Sharkord\Managers\ServerManager;
	use Sharkord\Managers\UserManager;
	use Sharkord\Commands\CommandRouter;
	use Sharkord\Models\User;
	use Sharkord\WebSocket\Gateway;

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

		public readonly Client  $http;
		public readonly Gateway $gateway;
		public readonly Guard $guard;
		public readonly LoopInterface $loop;

		public ChannelManager  $channels;
		public UserManager     $users;
		public CategoryManager $categories;
		public RoleManager     $roles;
		public ServerManager   $servers;
		public MessageManager  $messages;
		public CommandRouter   $commands;
		public LoggerInterface $logger;

		/** @var User|null The framework's own authenticated user object. */
		public ?User $bot = null;

		private readonly ReconnectHandler $reconnectHandler;

		/**
		 * Sharkord constructor.
		 *
		 * @param array                $config               Configuration array containing 'host', 'identity', and 'password'.
		 * @param LoopInterface|null   $loop                 The ReactPHP event loop instance.
		 * @param LoggerInterface|null $logger               A PSR-3 logger. A Monolog instance is created if omitted.
		 * @param string               $logLevel             Minimum log level when instantiating the default logger.
		 * @param bool                 $reconnect            Whether to attempt reconnection on disconnect.
		 * @param int                  $maxReconnectAttempts Maximum number of reconnect attempts before exiting.
		 */
		public function __construct(
			private readonly array   $config,
			?LoopInterface           $loop                 = null,
			?LoggerInterface         $logger               = null,
			string                   $logLevel             = 'Notice',
			private bool             $reconnect            = true,
			private readonly int     $maxReconnectAttempts = 5,
		) {

			foreach (['host', 'identity', 'password'] as $key) {
				if (empty($this->config[$key])) {
					throw new \InvalidArgumentException("Missing required config key: '{$key}'.");
				}
			}

			$this->loop   = $loop ?? Loop::get();
			$this->logger = $logger ?? LoggerFactory::create($logLevel);

			$this->channels   = new ChannelManager($this);
			$this->users      = new UserManager($this);
			$this->categories = new CategoryManager($this);
			$this->roles      = new RoleManager($this);
			$this->servers    = new ServerManager($this);
			$this->messages   = new MessageManager($this);
			$this->commands   = new CommandRouter($this);
			$this->guard 	  = new Guard($this);

			$this->http    = new Client($this->config, $this->loop, $this->logger);
			$this->gateway = new Gateway($this->config, $this->loop, $this->logger);

			$this->reconnectHandler = new ReconnectHandler(
				loop:        $this->loop,
				logger:      $this->logger,
				maxAttempts: $this->maxReconnectAttempts,
				connectFn:   fn() => $this->connect(),
				onSuccess:   function () {
					$this->logger->info("Reconnected successfully.");
					$this->emit('ready', [$this->bot]);
				},
				onExhausted: fn() => $this->loop->stop(),
			);

			$this->gateway->on('closed', function (int $code, string $reason) {

				$this->logger->warning("Gateway connection lost. Code: {$code}. Reason: {$reason}");

				if (!$this->reconnect) {
					$this->loop->stop();
					return;
				}

				$this->loop->futureTick(fn() => $this->reconnectHandler->attempt());

			});

		}

		/**
		 * Authenticates and starts the bot, then runs the event loop.
		 *
		 * @return void
		 */
		public function run(): void {

			$this->logger->debug("Starting Bot...");

			$this->connect()
				->then(function () {
					$this->reconnectHandler->reset();
					$this->logger->info("Bot is fully initialized and ready.");
					$this->emit('ready', [$this->bot]);
				})
				->catch(function (mixed $reason) {
					$this->logger->error("Fatal Startup Error: " . PromiseUtils::reasonToString($reason));
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
			$this->reconnect = false;
			$this->gateway->disconnect();
			$this->loop->stop();

		}

		/**
		 * Runs the full connection sequence: authenticate, connect, hydrate, subscribe.
		 *
		 * @return \React\Promise\PromiseInterface Resolves when the session is fully ready.
		 */
		private function connect(): \React\Promise\PromiseInterface {

			$session = new ConnectionSession($this, $this->logger);

			return $this->http->authenticate()
				->then(fn(string $token) => $this->gateway->connect($token))
				->then(fn(array $joinData) => $session->start($joinData));

		}

	}

?>