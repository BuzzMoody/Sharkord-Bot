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
		 *     echo $message->author->name . ': ' . $message->content;
		 * });
		 * ```
		 */
		private function onNewMessage(mixed $raw): void {

			if (!is_array($raw) || empty($raw)) {
				$this->logger->warning("Received messages.onNew event with an unexpected payload type: " . get_debug_type($raw));
				return;
			}

			$this->sharkord->messages->cache($raw);

			$message = $this->sharkord->messages->getFromCache($raw['id'])
				?? Message::fromArray($raw, $this->sharkord);

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
		 * Fired when a message is edited, pinned, unpinned, or its reactions change.
		 * This method emits two distinct events:
		 *
		 * - 'messageupdate' is always emitted with the full Message model.
		 * - 'messagereaction' is emitted only when the reactions collection has changed
		 *   since the last known cached state for that message. It receives the Message
		 *   model and a Reactions collection instance keyed by emoji shortcode.
		 *
		 * Reaction diffing is performed by reading the reactions stored on the previously
		 * cached Message before the update is applied, then comparing against the incoming
		 * payload. Because the full message is already cached, no separate reaction-tracking
		 * structure is needed.
		 *
		 * A missing cache entry is treated as an empty reaction set, so the very first
		 * reaction placed on a message correctly triggers 'messagereaction'. The message
		 * cache is reset on each new ConnectionSession (i.e. each reconnect), so the first
		 * update after a reconnect will also re-fire the event if reactions are present.
		 *
		 * @param mixed $raw The raw event payload. Expected to be a non-empty array.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on('messageupdate', function(Message $message) {
		 *     if ($message->isPinned()) {
		 *         echo "Message {$message->id} was just pinned.";
		 *     }
		 * });
		 *
		 * $sharkord->on('messagereaction', function(Message $message, Reactions $reactions) {
		 *     echo "Message {$message->id} now has " . count($reactions) . " emoji type(s):";
		 *     foreach ($reactions as $emoji => $group) {
		 *         echo " :{$emoji}: x{$group->count}";
		 *         foreach ($group->users as $user) {
		 *             echo " ({$user->name})";
		 *         }
		 *     }
		 * });
		 * ```
		 */
		private function onMessageUpdate(mixed $raw): void {

			if (!is_array($raw) || empty($raw)) {
				$this->logger->warning("Received messages.onUpdate event with an unexpected payload type: " . get_debug_type($raw));
				return;
			}

			// Read the previous reaction state from the cached message before applying
			// the update, so we have a baseline to diff against. Only bother doing this
			// when the incoming payload actually contains a reactions key.
			$previousReactions = [];
			$checkReactions    = array_key_exists('reactions', $raw);

			if ($checkReactions) {
				$cached            = $this->sharkord->messages->getFromCache($raw['id'] ?? '');
				$previousReactions = $cached?->toArray()['reactions'] ?? [];
			}

			$this->sharkord->messages->update($raw);

			// Prefer the fully-merged cached instance over a partial model built only
			// from the incoming diff payload.
			$message = $this->sharkord->messages->getFromCache($raw['id'] ?? '')
				?? Message::fromArray($raw, $this->sharkord);

			try {
				$this->sharkord->emit('messageupdate', [$message]);
			} catch (\Throwable $e) {
				$this->logger->error(sprintf(
					"Uncaught error in 'messageupdate' handler: %s on line %d in %s",
					$e->getMessage(), $e->getLine(), $e->getFile()
				));
			}

			if (!$checkReactions) {
				return;
			}

			$newReactions = $raw['reactions'] ?? [];

			if (!$this->reactionsChanged($previousReactions, $newReactions)) {
				return;
			}

			$reactionObjects = array_map(
				fn(array $r) => MessageReaction::fromArray($r, $this->sharkord),
				$newReactions
			);

			try {
				$this->sharkord->emit('messagereaction', [$message, $reactionObjects]);
			} catch (\Throwable $e) {
				$this->logger->error(sprintf(
					"Uncaught error in 'messagereaction' handler: %s on line %d in %s",
					$e->getMessage(), $e->getLine(), $e->getFile()
				));
			}

		}

		/**
		 * Handles an incoming message delete event from the gateway.
		 *
		 * Fired when any message is deleted by any user. Because a deleted message no
		 * longer exists on the server, only the identifying fields supplied by the API
		 * are available — a full Message model cannot be reconstructed. The raw payload
		 * array is emitted directly so listeners can act on the IDs they receive.
		 *
		 * The payload is guaranteed to contain a scalar 'id' for the deleted message.
		 * 'channelId' is included by the server where available but must be treated as
		 * optional by listeners.
		 *
		 * @param mixed $raw The raw event payload. Expected to be an array containing
		 *                   at least a scalar 'id' field. 'channelId' may or may not
		 *                   be present depending on the server payload.
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
		private function onMessageDelete(mixed $raw): void {

			if (!is_array($raw) || empty($raw)) {
				$this->logger->warning("Received messages.onDelete event with an unexpected payload type: " . get_debug_type($raw));
				return;
			}

			if (!isset($raw['id']) || !is_scalar($raw['id'])) {
				$this->logger->warning("Received messages.onDelete event with a missing or non-scalar 'id' in payload.");
				return;
			}

			$this->sharkord->messages->remove($raw['id']);

			try {
				$this->sharkord->emit('messagedelete', [$raw]);
			} catch (\Throwable $e) {
				$this->logger->error(sprintf(
					"Uncaught error in 'messagedelete' handler: %s on line %d in %s",
					$e->getMessage(), $e->getLine(), $e->getFile()
				));
			}

		}

		/**
		 * Handles an incoming typing indicator event from the gateway.
		 *
		 * Fired when a user begins typing in a channel. Both the User and Channel
		 * models must be resolvable from the cache for the event to be emitted —
		 * if either is missing the event is silently dropped.
		 *
		 * @param mixed $raw The raw event payload. Expected to be an array containing
		 *                   'userId' and 'channelId' fields.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on('messagetyping', function(User $user, Channel $channel) {
		 *     echo "{$user->name} is typing in #{$channel->name}...";
		 * });
		 * ```
		 */
		private function onMessageTyping(mixed $raw): void {

			if (!is_array($raw) || empty($raw)) {
				$this->logger->warning("Received messages.onTyping event with an unexpected payload type: " . get_debug_type($raw));
				return;
			}

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

		/**
		 * Determines whether two raw reaction arrays represent a different state.
		 *
		 * Comparison is performed against a normalised set of userId+emoji pairs so
		 * that irrelevant field differences (e.g. timestamp precision) do not cause
		 * false positives, and ordering of the reactions array is ignored.
		 *
		 * @param array $previous The previously cached reactions array.
		 * @param array $current  The incoming reactions array from the server.
		 * @return bool True if the reactions have changed, false if they are identical.
		 */
		private function reactionsChanged(array $previous, array $current): bool {

			return $this->normalizeReactions($previous) !== $this->normalizeReactions($current);

		}

		/**
		 * Normalises a raw reactions array into a sorted, comparable string set.
		 *
		 * Each reaction is reduced to a "userId:emoji" key. The resulting array is
		 * sorted so that insertion-order differences don't affect equality checks.
		 *
		 * @param array $reactions The raw reactions array from the API payload.
		 * @return array<string> A sorted array of "userId:emoji" identity strings.
		 */
		private function normalizeReactions(array $reactions): array {

			$keys = array_map(
				fn(array $r) => ($r['userId'] ?? '') . ':' . ($r['emoji'] ?? ''),
				$reactions
			);

			sort($keys);

			return $keys;

		}

	}
	
?>