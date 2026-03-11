<?php

	declare(strict_types=1);

	namespace Sharkord\Collections;

	use Sharkord\Sharkord;
	use Sharkord\Models\Channel;

	/**
	 * Class Channels
	 *
	 * An array-accessible, iterable cache of Channel objects keyed by channel ID (string).
	 *
	 * Supports lookup by both integer ID and channel name via get().
	 *
	 * @implements \ArrayAccess<string, Channel>
	 * @implements \IteratorAggregate<string, Channel>
	 *
	 * @package Sharkord\Collections
	 *
	 * @example
	 * ```php
	 * // Look up by ID or name
	 * $channel = $sharkord->channels->collection()->get(1);
	 * $channel = $sharkord->channels->collection()->get('general');
	 *
	 * // Iterate all cached channels
	 * foreach ($sharkord->channels->collection() as $id => $channel) {
	 *     echo "{$channel->name}\n";
	 * }
	 *
	 * echo count($sharkord->channels->collection());
	 * ```
	 */
	class Channels implements \ArrayAccess, \Countable, \IteratorAggregate {

		/**
		 * @var array<string, Channel> Cached Channel models keyed by channel ID (string).
		 */
		private array $channels = [];

		/**
		 * Channels constructor.
		 *
		 * @param Sharkord $sharkord Reference to the main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {}

		/**
		 * Adds or merges a channel into the collection.
		 *
		 * @internal
		 * @param array $raw The raw channel data from the server.
		 * @return void
		 */
		public function add(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot cache channel: missing 'id' in data.");
				return;
			}

			$id = (string) $raw['id'];

			if (isset($this->channels[$id])) {
				$this->channels[$id]->updateFromArray($raw);
				return;
			}

			$this->channels[$id] = Channel::fromArray($raw, $this->sharkord);

		}

		/**
		 * Merges new data into an already-cached channel.
		 *
		 * @internal
		 * @param array $raw The raw channel data from the server.
		 * @return Channel|null The updated Channel model, or null if not cached.
		 */
		public function update(array $raw): ?Channel {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot update cached channel: missing 'id' in data.");
				return null;
			}

			$id = (string) $raw['id'];

			if (!isset($this->channels[$id])) {
				return null;
			}

			$this->channels[$id]->updateFromArray($raw);

			return $this->channels[$id];

		}

		/**
		 * Removes a channel from the collection.
		 *
		 * @internal
		 * @param int|string $id The channel ID.
		 * @return void
		 */
		public function remove(int|string $id): void {

			unset($this->channels[(string) $id]);

		}

		/**
		 * Retrieves a channel by ID or name.
		 *
		 * @param int|string $identifier The channel ID or name.
		 * @return Channel|null
		 */
		public function get(int|string $identifier): ?Channel {

			if (is_int($identifier) || ctype_digit((string) $identifier)) {
				return $this->channels[(string)(int) $identifier] ?? null;
			}

			foreach ($this->channels as $channel) {
				if ($channel->name === $identifier) {
					return $channel;
				}
			}

			return null;

		}

		// --- ArrayAccess ---

		public function offsetExists(mixed $offset): bool {

			return isset($this->channels[(string) $offset]);

		}

		public function offsetGet(mixed $offset): ?Channel {

			return $this->channels[(string) $offset] ?? null;

		}

		/** @throws \LogicException */
		public function offsetSet(mixed $offset, mixed $value): void {

			throw new \LogicException('Channels is read-only. Use add() to cache a channel.');

		}

		/** @throws \LogicException */
		public function offsetUnset(mixed $offset): void {

			throw new \LogicException('Channels is read-only. Use remove() to evict a channel.');

		}

		// --- Countable ---

		public function count(): int {

			return count($this->channels);

		}

		// --- IteratorAggregate ---

		/**
		 * @return \ArrayIterator<string, Channel>
		 */
		public function getIterator(): \ArrayIterator {

			return new \ArrayIterator($this->channels);

		}

	}
	
?>