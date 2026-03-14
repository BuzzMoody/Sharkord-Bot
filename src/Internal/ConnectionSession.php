<?php

	declare(strict_types=1);

	namespace Sharkord\Internal;

	use Psr\Log\LoggerInterface;
	use React\Promise\PromiseInterface;
	use function React\Promise\resolve;

	use Sharkord\Sharkord;
	use Sharkord\Models\Message;
	use Sharkord\Collections\Reactions;

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

			foreach ($raw['roles']      ?? [] as $r) { $this->sharkord->roles->hydrate($r);       }
			foreach ($raw['categories'] ?? [] as $c) { $this->sharkord->categories->hydrate($c);  }
			foreach ($raw['channels']   ?? [] as $c) { $this->sharkord->channels->hydrate($c);    }
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
				'messages.onDelete' => fn($d) => $this->onMessageDelete($d),
				'messages.onTyping' => fn($d) => $this->onMessageTyping($d),

				'channels.onCreate' => fn($d) => $this->sharkord->channels->onCreate($d),
				'channels.onUpdate' => fn($d) => $this->sharkord->channels->onUpdate($d),
				'channels.onDelete' => fn($d) => $this->sharkord->channels->onDelete($d),

				'users.onCreate'    => fn($d) => $this->sharkord->users->onCreate($d),
				'users.onJoin'      => fn($d) => $this->sharkord->users->onJoin($d),
				'users.onLeave'     => fn($d) => $this->sharkord->users->onLeave($d),
				'users.onUpdate'    => fn($d) => $this->sharkord->users->onUpdate($d),
				'users.onDelete'    => fn($d) => $this->sharkord->users->onDelete($d),

				'roles.onCreate'    => fn($d) => $this->sharkord->roles->onCreate($d),
				'roles.onUpdate'    => fn($d) => $this->sharkord->roles->onUpdate($d),
				'roles.onDelete'    => fn($d) => $this->sharkord->roles->onDelete($d),

				'categories.onCreate' => fn($d) => $this->sharkord->categories->onCreate($d),
				'categories.onUpdate' => fn($d) => $this->sharkord->categories->onUpdate($d),
				'categories.onDelete' => fn($d) => $this->sharkord->categories->onDelete($d),

				'others.onServerSettingsUpdate' => fn($d) => $this->sharkord->servers->onUpdate($d),
			];

			foreach ($subscriptions as $path => $callback) {

				$this->sharkord->gateway->subscribeRpc(
					$path,
					function (mixed $eventData) use ($callback, $path) {
						try {
							$callback($eventData);
						} catch (\Throwable $e) {
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
		 * The message is stored in the MessageManager cache so it is available
		 * to subsequent update and reaction events without an API round-trip.
		 *
		 * @param mixed $raw The raw event payload. Expected to be a non-empty array.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on('message', function(Message $message) {
		 *     echo $message->author->name . ': ' . $message->content . "\n";
		 * });
		 * ```
		 */
		private function onNewMessage(mixed $raw): void {

			if (!is_array($raw) || empty($raw)) {
				$this->logger->warning("Received malformed new-message payload.");
				return;
			}

			$message = $this->sharkord->messages->onCreate($raw);

			if (!$message) {
				$this->logger->warning("Failed to cache incoming message.");
				return;
			}

			$this->sharkord->emit('message', [$message]);

		}

		/**
		 * Handles an incoming message update event from the gateway.
		 *
		 * Only emits `messageupdate` and `messagereaction` if the message is already
		 * present in the local cache. Updates for uncached messages (e.g. those that
		 * arrived before the current session or were evicted) are silently dropped to
		 * avoid emitting partially-populated Message objects built from diff-only payloads.
		 *
		 * @param mixed $raw The raw event payload. Expected to be a non-empty array with an `id` key.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::MESSAGE_UPDATE, function(\Sharkord\Models\Message $message): void {
		 *     echo "Message {$message->id} was updated.\n";
		 * });
		 *
		 * $sharkord->on(\Sharkord\Events::MESSAGE_REACTION, function(
		 *     \Sharkord\Models\Message        $message,
		 *     \Sharkord\Collections\Reactions $reactions,
		 * ): void {
		 *     echo "Message {$message->id} now has " . count($reactions) . " emoji type(s).\n";
		 * });
		 * ```
		 */
		private function onMessageUpdate(mixed $raw): void {

			if (!is_array($raw) || !isset($raw['id'])) {
				$this->logger->warning("Received malformed message-update payload.");
				return;
			}

			$checkReactions    = array_key_exists('reactions', $raw);
			$previousReactions = [];

			if ($checkReactions) {
				$cached            = $this->sharkord->messages->getFromCache($raw['id']);
				$previousReactions = $cached?->toArray()['reactions'] ?? [];
			}

			$message = $this->sharkord->messages->onUpdate($raw);

			if (!$message) {
				return;
			}

			$this->sharkord->emit('messageupdate', [$message]);

			if (!$checkReactions) {
				return;
			}

			$newReactions = $raw['reactions'] ?? [];

			if ($previousReactions === $newReactions) {
				return;
			}

			$this->sharkord->emit('messagereaction', [$message, new Reactions($this->sharkord, $newReactions)]);

		}

		/**
		 * Handles an incoming message delete event from the gateway.
		 *
		 * Because the message no longer exists on the server the full model cannot
		 * be reconstructed. The raw payload array is passed to the event directly.
		 *
		 * The server sends `messageId` as the identifier key; this is normalised to
		 * `id` for consistency with the rest of the framework before the event is
		 * emitted.
		 *
		 * @param mixed $raw The raw event payload. Expected to contain at minimum a `messageId` key.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::MESSAGE_DELETE, function(array $data): void {
		 *     echo "Message {$data['id']} was deleted from channel {$data['channelId']}.\n";
		 * });
		 * ```
		 */
		private function onMessageDelete(mixed $raw): void {

			if (!is_array($raw) || !isset($raw['messageId'])) {
				$this->logger->warning("Received malformed message-delete payload.");
				return;
			}

			$raw['id'] = $raw['messageId'];
			unset($raw['messageId']);

			$this->sharkord->messages->onDelete($raw['id']);

			$this->sharkord->emit('messagedelete', [$raw]);

		}

		/**
		 * Handles an incoming typing indicator event from the gateway.
		 *
		 * Both the User and Channel must be resolvable from cache; if either is
		 * missing the event is silently dropped to avoid emitting incomplete data.
		 *
		 * @param mixed $raw The raw event payload. Expected to contain `userId` and `channelId`.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::MESSAGE_TYPING, function(
		 *     \Sharkord\Models\User    $user,
		 *     \Sharkord\Models\Channel $channel,
		 * ): void {
		 *     echo "{$user->name} is typing in #{$channel->name}...\n";
		 * });
		 * ```
		 */
		private function onMessageTyping(mixed $raw): void {

			if (!is_array($raw) || !isset($raw['userId'], $raw['channelId'])) {
				$this->logger->warning("Received malformed typing payload.");
				return;
			}

			$user    = $this->sharkord->users->get($raw['userId']);
			$channel = $this->sharkord->channels->get($raw['channelId']);

			if (!$user || !$channel) {
				return;
			}

			$this->sharkord->emit('messagetyping', [$user, $channel]);

		}

	}

?>