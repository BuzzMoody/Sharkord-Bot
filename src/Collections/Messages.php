<?php

	declare(strict_types=1);

	namespace Sharkord\Collections;

	use Sharkord\Sharkord;
	use Sharkord\Models\Message;

	/**
	 * Class Messages
	 *
	 * A bounded, array-accessible, iterable cache of Message objects keyed by
	 * message ID (string).
	 *
	 * Owns all cache storage and eviction logic so that MessageManager can focus
	 * solely on API interactions. When the cache reaches its size limit the oldest
	 * inserted entry is evicted (FIFO) to make room for the new one. PHP arrays
	 * preserve insertion order, so eviction is a cheap reset()/key()/unset().
	 *
	 * The collection is intentionally write-protected from outside callers —
	 * ArrayAccess mutations throw a LogicException. All writes go through the
	 * explicit add(), update(), and remove() methods so eviction is always applied.
	 *
	 * @implements \ArrayAccess<string, Message>
	 * @implements \IteratorAggregate<string, Message>
	 *
	 * @package Sharkord\Collections
	 *
	 * @example
	 * ```php
	 * // Retrieve a cached message directly (no API call)
	 * $message = $sharkord->messages->getFromCache(609);
	 *
	 * // Iterate all cached messages
	 * foreach ($sharkord->messages->cached() as $id => $message) {
	 *     echo "{$id}: {$message->content}\n";
	 * }
	 *
	 * // Check how many messages are currently cached
	 * echo $sharkord->messages->count();
	 * ```
	 */
	class Messages implements \ArrayAccess, \Countable, \IteratorAggregate {

		/**
		 * @var array<string, Message> Cached Message models keyed by message ID (string).
		 *                             Insertion order is preserved for FIFO eviction.
		 */
		private array $messages = [];

		/**
		 * Messages constructor.
		 *
		 * @param Sharkord $sharkord    Reference to the main bot instance.
		 * @param int      $maxSize     Maximum number of messages to hold in memory at once.
		 *                              When the limit is reached the oldest entry is evicted.
		 *                              Defaults to 500.
		 */
		public function __construct(
			private readonly Sharkord $sharkord,
			private readonly int $maxSize = 500
		) {}

		/**
		 * Adds or merges a message into the cache.
		 *
		 * If the message ID is already present its data is merged in place so the
		 * existing object reference remains valid. If it is a new entry and the cache
		 * is full, the oldest entry is evicted before inserting.
		 *
		 * @internal This method is for internal framework use only. Do not call this directly.
		 * @param array $raw The raw message data from the server.
		 * @return void
		 */
		public function add(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot cache message: missing 'id' in data.");
				return;
			}

			$id = (string) $raw['id'];

			if (isset($this->messages[$id])) {
				$this->messages[$id]->updateFromArray($raw);
				return;
			}

			if (count($this->messages) >= $this->maxSize) {
				reset($this->messages);
				unset($this->messages[key($this->messages)]);
			}

			$this->messages[$id] = Message::fromArray($raw, $this->sharkord);

		}

		/**
		 * Merges new data into an already-cached message.
		 *
		 * If the message is not in the cache this is a no-op — the change will be
		 * reflected the next time the message is fetched via MessageManager::get().
		 *
		 * @internal This method is for internal framework use only. Do not call this directly.
		 * @param array $raw The raw message data from the server.
		 * @return Message|null The updated Message model, or null if it was not cached.
		 */
		public function update(array $raw): ?Message {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot update cached message: missing 'id' in data.");
				return null;
			}

			$id = (string) $raw['id'];

			if (!isset($this->messages[$id])) {
				return null;
			}

			$this->messages[$id]->updateFromArray($raw);

			return $this->messages[$id];

		}

		/**
		 * Removes a message from the cache.
		 *
		 * @internal This method is for internal framework use only. Do not call this directly.
		 * @param int|string $id The ID of the message to remove.
		 * @return void
		 */
		public function remove(int|string $id): void {

			unset($this->messages[(string) $id]);

		}

		/**
		 * Retrieves a cached message by ID without hitting the API.
		 *
		 * @param int|string $id The message ID.
		 * @return Message|null The cached Message model, or null if not found.
		 */
		public function get(int|string $id): ?Message {

			return $this->messages[(string) $id] ?? null;

		}

		/**
		 * Returns all currently cached messages as an array keyed by message ID.
		 *
		 * @return array<string, Message>
		 */
		public function cached(): array {

			return $this->messages;

		}

		// --- ArrayAccess ---

		/**
		 * @param int|string $offset The message ID.
		 */
		public function offsetExists(mixed $offset): bool {

			return isset($this->messages[(string) $offset]);

		}

		/**
		 * @param int|string $offset The message ID.
		 * @return Message|null
		 */
		public function offsetGet(mixed $offset): ?Message {

			return $this->messages[(string) $offset] ?? null;

		}

		/** @throws \LogicException Message collections are read-only via array access. */
		public function offsetSet(mixed $offset, mixed $value): void {

			throw new \LogicException('Messages is read-only. Use add() to cache a message.');

		}

		/** @throws \LogicException Message collections are read-only via array access. */
		public function offsetUnset(mixed $offset): void {

			throw new \LogicException('Messages is read-only. Use remove() to evict a message.');

		}

		// --- Countable ---

		/**
		 * Returns the number of messages currently held in the cache.
		 *
		 * @return int
		 */
		public function count(): int {

			return count($this->messages);

		}

		// --- IteratorAggregate ---

		/**
		 * @return \ArrayIterator<string, Message>
		 */
		public function getIterator(): \ArrayIterator {

			return new \ArrayIterator($this->messages);

		}

	}
	
?>