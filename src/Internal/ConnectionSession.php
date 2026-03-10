<?php

	declare(strict_types=1);

	namespace Sharkord\Internal;

	use Psr\Log\LoggerInterface;
	use React\Promise\PromiseInterface;
	use function React\Promise\resolve;
	use Sharkord\Models\Message;
	use Sharkord\Sharkord;

	/**
	 * Class ConnectionSession
	 *
	 * Manages a single connected session's lifecycle: entity hydration,
	 * subscription registration, and real-time event dispatching.
	 *
	 * A new instance should be created for each connection attempt.
	 *
	 * @package Sharkord\Internal
	 */
	class ConnectionSession {

		/**
		 * ConnectionSession constructor.
		 *
		 * @param Sharkord        $sharkord The main bot instance.
		 * @param LoggerInterface $logger   The PSR-3 logger instance.
		 */
		public function __construct(
			private readonly Sharkord        $sharkord,
			private readonly LoggerInterface $logger,
		) {}

		/**
		 * Runs the full post-connection lifecycle: hydrates entities and registers subscriptions.
		 *
		 * @param array $joinData The raw join response payload from the gateway.
		 * @return PromiseInterface Resolves when the session is fully ready.
		 */
		public function start(array $joinData): PromiseInterface {

			return $this->hydrateElements($joinData)
				->then(fn() => $this->setupSubscriptions());

		}

		/**
		 * Populates manager caches from the initial join response payload.
		 *
		 * @param array $data The raw join response payload.
		 * @return PromiseInterface Resolves when hydration is complete.
		 * @throws \RuntimeException If the payload is malformed.
		 */
		private function hydrateElements(array $data): PromiseInterface {

			if (!isset($data['data']) || !is_array($data['data'])) {
				throw new \RuntimeException("Invalid join response: missing 'data' payload.");
			}

			$raw = $data['data'];

			foreach ($raw['roles']      ?? [] as $r) { $this->sharkord->roles->hydrate($r);      }
			foreach ($raw['categories'] ?? [] as $c) { $this->sharkord->categories->hydrate($c); }
			foreach ($raw['channels']   ?? [] as $c) { $this->sharkord->channels->hydrate($c);   }
			foreach ($raw['users']      ?? [] as $u) { $this->sharkord->users->hydrate($u);       }

			if (!isset($raw['ownUserId'])) {
				throw new \RuntimeException("Invalid join response: missing 'ownUserId'.");
			}

			$this->sharkord->bot = $this->sharkord->users->get($raw['ownUserId'])
				?? throw new \RuntimeException(sprintf(
					"Bot user with ID '%s' not found in hydrated users list.",
					(string) $raw['ownUserId']
				));

			$this->sharkord->servers->hydrate($raw['publicSettings'] ?? []);

			$this->logger->info(sprintf(
				"Connected! Cached %d channels, %d users.",
				$this->sharkord->channels->count(),
				$this->sharkord->users->count()
			));

			return resolve(null);

		}

		/**
		 * Registers all real-time event subscriptions with the gateway.
		 *
		 * @return PromiseInterface Resolves when all subscriptions are registered.
		 */
		private function setupSubscriptions(): PromiseInterface {

			$subscriptions = [
				'messages.onNew'    => fn($d) => $this->onNewMessage($d),
				'messages.onUpdate' => fn($d) => $this->onMessageUpdate($d),
				'messages.onTyping' => fn($d) => $this->onMessageTyping($d),

				'channels.onCreate' => fn($d) => $this->sharkord->channels->create($d),
				'channels.onDelete' => fn($d) => $this->sharkord->channels->delete($d),
				'channels.onUpdate' => fn($d) => $this->sharkord->channels->update($d),

				'users.onCreate'    => fn($d) => $this->sharkord->users->create($d),
				'users.onJoin'      => fn($d) => $this->sharkord->users->join($d),
				'users.onLeave'     => fn($d) => $this->sharkord->users->leave($d),
				'users.onUpdate'    => fn($d) => $this->sharkord->users->update($d),
				'users.onDelete'    => fn($d) => $this->sharkord->users->delete($d),

				'roles.onCreate'    => fn($d) => $this->sharkord->roles->create($d),
				'roles.onUpdate'    => fn($d) => $this->sharkord->roles->update($d),
				'roles.onDelete'    => fn($d) => $this->sharkord->roles->delete($d),

				'categories.onCreate' => fn($d) => $this->sharkord->categories->create($d),
				'categories.onUpdate' => fn($d) => $this->sharkord->categories->update($d),
				'categories.onDelete' => fn($d) => $this->sharkord->categories->delete($d),

				'others.onServerSettingsUpdate' => fn($d) => $this->sharkord->servers->update($d),
			];

			foreach ($subscriptions as $path => $callback) {

				$this->sharkord->gateway->subscribeRpc(
					$path,
					function (mixed $eventData) use ($callback, $path) {
						try {
							$callback($eventData);
						} catch (\Exception $e) {
							$this->logger->error(
								"Error processing event for {$path}: " . $e->getMessage()
							);
						}
					}
				);

				$this->logger->debug("Subscribing to event stream: {$path}");

			}

			return resolve(null);

		}

		/**
		 * Handles an incoming new message event from the gateway.
		 *
		 * @param array $raw The raw message payload.
		 * @return void
		 */
		private function onNewMessage(array $raw): void {

			$message = Message::fromArray($raw, $this->sharkord);

			try {
				$this->sharkord->emit('message', [$message]);
			} catch (\Throwable $e) {
				$this->logger->error(sprintf(
					"Uncaught error in 'message' handler: %s on line %d in %s",
					$e->getMessage(), $e->getLine(), $e->getFile()
				));
			}

		}

		/**
		 * Handles an incoming message update event from the gateway.
		 *
		 * @param array $raw The raw message payload.
		 * @return void
		 */
		private function onMessageUpdate(array $raw): void {

			$message = Message::fromArray($raw, $this->sharkord);

			try {
				$this->sharkord->emit('messageupdate', [$message]);
			} catch (\Throwable $e) {
				$this->logger->error(sprintf(
					"Uncaught error in 'messageupdate' handler: %s on line %d in %s",
					$e->getMessage(), $e->getLine(), $e->getFile()
				));
			}

		}
		
		/**
		 * Handles an incoming typing indicator event from the gateway.
		 *
		 * @param array $raw The raw typing payload containing userId and channelId.
		 * @return void
		 */
		private function onMessageTyping(array $raw): void {

			$user    = $this->sharkord->users->get($raw['userId'] ?? 0);
			$channel = $this->sharkord->channels->get($raw['channelId'] ?? 0);

			if (!$user || !$channel) {
				return;
			}

			try {
				$this->sharkord->emit('messagetyping', [$user, $channel]);
			} catch (\Throwable $e) {
				$this->logger->error(sprintf(
					"Uncaught error in 'messagetyping' handler: %s on line %d in %s",
					$e->getMessage(), $e->getLine(), $e->getFile()
				));
			}

		}

	}

?>