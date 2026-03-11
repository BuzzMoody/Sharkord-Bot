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
	 * Manages message API interactions, delegating all cache storage and eviction
	 * to a Messages collection instance.
	 *
	 * @package Sharkord\Managers
	 */
	class MessageManager {

		private MessagesCollection $cache;

		/**
		 * MessageManager constructor.
		 *
		 * @param Sharkord $sharkord    The main bot instance.
		 * @param int      $maxCacheSize Maximum number of messages to hold in memory at once.
		 *                              Passed through to the underlying Messages collection.
		 *                              Defaults to 500.
		 */
		public function __construct(
			private readonly Sharkord $sharkord,
			int $maxCacheSize = 500
		) {
			$this->cache = new MessagesCollection($this->sharkord, $maxCacheSize);
		}

		/**
		 * Adds or merges a message into the cache.
		 *
		 * @internal This method is for internal framework use only. Do not call this directly.
		 * @param array $raw The raw message data from the server.
		 * @return void
		 */
		public function cache(array $raw): void {

			$this->cache->add($raw);

		}

		/**
		 * Merges new data into an already-cached message.
		 *
		 * @internal This method is for internal framework use only. Do not call this directly.
		 * @param array $raw The raw message data from the server.
		 * @return Message|null The updated Message model, or null if it was not cached.
		 */
		public function update(array $raw): ?Message {

			return $this->cache->update($raw);

		}

		/**
		 * Removes a message from the cache.
		 *
		 * @internal This method is for internal framework use only. Do not call this directly.
		 * @param int|string $id The ID of the message to remove.
		 * @return void
		 */
		public function remove(int|string $id): void {

			$this->cache->remove($id);

		}

		/**
		 * Retrieves a cached message by ID without hitting the API.
		 *
		 * @param int|string $id The message ID.
		 * @return Message|null The cached Message model, or null if not found.
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
		 */
		public function count(): int {

			return count($this->cache);

		}

		/**
		 * Retrieves a message by ID, checking the local cache before falling back to the API.
		 *
		 * @param int|string $messageId The ID of the message to retrieve.
		 * @param int|string $channelId The ID of the channel the message belongs to.
		 *                              Only used when the message is not found in the cache.
		 * @return PromiseInterface Resolves with a Message object, or rejects if not found.
		 *
		 * @example
		 * ```php
		 * $sharkord->messages->get(609, 1)->then(function(Message $message) {
		 *     echo $message->content;
		 * });
		 * ```
		 */
		public function get(int|string $messageId, int|string $channelId): PromiseInterface {

			$cached = $this->cache->get($messageId);

			if ($cached !== null) {
				return \React\Promise\resolve($cached);
			}

			return $this->sharkord->gateway->sendRpc("query", [
				"input" => [
					"channelId"       => $channelId,
					"targetMessageId" => $messageId,
					"cursor"          => null,
				],
				"path" => "messages.get"
			])->then(function ($response) use ($messageId) {

				$messages     = $response['data']['messages'] ?? [];
				$normalizedId = (string) $messageId;

				if (isset($messages[20]) && (string) $messages[20]['id'] === $normalizedId) {
					$this->cache->add($messages[20]);
					return $this->cache->get($normalizedId);
				}

				foreach ($messages as $raw) {
					if ((string) $raw['id'] === $normalizedId) {
						$this->cache->add($raw);
						return $this->cache->get($normalizedId);
					}
				}

				throw new \RuntimeException(
					"Message ID {$messageId} was not found in the server response."
				);

			});

		}

	}

?>