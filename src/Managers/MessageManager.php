<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Collections\Messages as MessagesCollection;
	use Sharkord\Models\Message;
	use React\Promise\PromiseInterface;

	/**
	 * Class MessageManager
	 *
	 * Manages message cache interactions, delegating all cache storage and eviction
	 * to a Messages collection instance.
	 *
	 * Accessible via `$sharkord->messages`.
	 *
	 * @package Sharkord\Managers
	 *
	 * @example
	 * ```php
	 * // Retrieve a cached message without an API call
	 * $message = $sharkord->messages->getFromCache(609);
	 *
	 * // Iterate all cached messages
	 * foreach ($sharkord->messages->collection() as $id => $message) {
	 *     echo "{$id}: {$message->content}\n";
	 * }
	 *
	 * // Fetch messages from a channel by ID
	 * $sharkord->messages->get(channelId: 12, limit: 25)
	 *     ->then(function(array $messages) {
	 *         foreach ($messages as $message) {
	 *             echo "{$message->author->name}: {$message->content}\n";
	 *         }
	 *     });
	 * ```
	 */
	class MessageManager {

		private MessagesCollection $cache;

		/**
		 * MessageManager constructor.
		 *
		 * @param Sharkord $sharkord     The main bot instance.
		 * @param int      $maxCacheSize Maximum number of messages to hold in memory at once.
		 *                               Passed through to the underlying Messages collection.
		 *                               Defaults to 500.
		 */
		public function __construct(
			private readonly Sharkord $sharkord,
			int $maxCacheSize = 500
		) {
			$this->cache = new MessagesCollection($this->sharkord, $maxCacheSize);
		}

		/**
		 * Handles a new message event by adding or merging it into the cache.
		 *
		 * @internal This method is for internal framework use only. Do not call this directly.
		 * @param array $raw The raw message data from the server.
		 * @return Message|null The cached Message model, or null if caching failed.
		 */
		public function onCreate(array $raw): ?Message {

			$this->cache->add($raw);

			return isset($raw['id']) ? $this->cache->get($raw['id']) : null;

		}

		/**
		 * Handles a message update event by merging new data into the cached model.
		 *
		 * @internal This method is for internal framework use only. Do not call this directly.
		 * @param array $raw The raw message data from the server.
		 * @return Message|null The updated Message model, or null if it was not cached.
		 */
		public function onUpdate(array $raw): ?Message {

			return $this->cache->update($raw);

		}

		/**
		 * Handles a message delete event by removing the model from the cache.
		 *
		 * @internal This method is for internal framework use only. Do not call this directly.
		 * @param int|string $id The ID of the message to remove.
		 * @return void
		 */
		public function onDelete(int|string $id): void {

			$this->cache->remove($id);

		}

		/**
		 * Retrieves a cached message by ID without hitting the API.
		 *
		 * @param int|string $id The message ID.
		 * @return Message|null The cached Message model, or null if not found.
		 *
		 * @example
		 * ```php
		 * $message = $sharkord->messages->getFromCache(609);
		 *
		 * if ($message) {
		 *     echo $message->content;
		 * }
		 * ```
		 */
		public function getFromCache(int|string $id): ?Message {

			return $this->cache->get($id);

		}

		/**
		 * Returns the underlying Messages collection.
		 *
		 * Useful when you want to iterate, count, or array-access the cache directly.
		 *
		 * @return MessagesCollection
		 *
		 * @example
		 * ```php
		 * foreach ($sharkord->messages->collection() as $id => $message) {
		 *     echo "{$id}: {$message->content}\n";
		 * }
		 *
		 * echo count($sharkord->messages->collection());
		 * ```
		 */
		public function collection(): MessagesCollection {

			return $this->cache;

		}

		/**
		 * Returns the number of messages currently held in the cache.
		 *
		 * @return int
		 *
		 * @example
		 * ```php
		 * echo "Cached messages: " . $sharkord->messages->count() . "\n";
		 * ```
		 */
		public function count(): int {

			return count($this->cache);

		}

		/**
		 * Fetches messages from a channel via the API.
		 *
		 * Uses the messages.get RPC. If a targetMessageId is provided the server
		 * returns the 20 messages before it plus all newer messages regardless of
		 * limit — this enables an efficient index-20 fast path for history traversal.
		 * All returned messages are merged into the local cache.
		 *
		 * @param int      $channelId       The ID of the channel to fetch from.
		 * @param int      $limit           Maximum number of messages to return. Defaults to 50.
		 * @param int|null $targetMessageId If set, fetches messages relative to this message ID.
		 * @return PromiseInterface Resolves with an array of Message models, rejects on failure.
		 *
		 * @example
		 * ```php
		 * // Fetch the latest 50 messages in a channel
		 * $sharkord->messages->get(channelId: 12, limit: 50)
		 *     ->then(function(array $messages) {
		 *         foreach ($messages as $message) {
		 *             echo "{$message->author->name}: {$message->content}\n";
		 *         }
		 *     });
		 *
		 * // Fetch messages relative to a known message ID
		 * $sharkord->messages->get(channelId: 12, targetMessageId: 609)
		 *     ->then(function(array $messages) {
		 *         echo "Fetched " . count($messages) . " messages.\n";
		 *     });
		 * ```
		 */
		public function get(
			int $channelId,
			int $limit = 50,
			?int $targetMessageId = null,
		): PromiseInterface {

			$input = [
				"channelId" => $channelId,
				"limit"     => $limit,
			];

			if ($targetMessageId !== null) {
				$input['targetMessageId'] = $targetMessageId;
			}

			return $this->sharkord->gateway->sendRpc("query", [
				"input" => $input,
				"path"  => "messages.get",
			])->then(function (array $response) {

				$rawMessages = $response['data']
					?? throw new \RuntimeException("messages.get response missing 'data'.");

				foreach ($rawMessages as $raw) {
					$this->cache->add($raw);
				}

				return array_map(
					fn(array $raw) => $this->cache->get($raw['id']) ?? Message::fromArray($raw, $this->sharkord),
					$rawMessages
				);

			});

		}

	}

?>