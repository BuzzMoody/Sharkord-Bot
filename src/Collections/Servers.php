<?php

	declare(strict_types=1);

	namespace Sharkord\Collections;

	use Sharkord\Sharkord;
	use Sharkord\Models\Server;

	/**
	 * Class Servers
	 *
	 * An array-accessible, iterable cache of Server objects keyed by server ID (string).
	 *
	 * In practice SharkordPHP connects to a single server at a time, so getFirst()
	 * is the most commonly used method. The full collection interface is provided for
	 * consistency and forward compatibility.
	 *
	 * @implements \ArrayAccess<string, Server>
	 * @implements \IteratorAggregate<string, Server>
	 *
	 * @package Sharkord\Collections
	 *
	 * @example
	 * ```php
	 * // Single-server bots
	 * $server = $sharkord->servers->collection()->getFirst();
	 *
	 * // Look up by ID
	 * $server = $sharkord->servers->collection()->get('abc123');
	 *
	 * foreach ($sharkord->servers->collection() as $id => $server) {
	 *     echo "{$server->name}\n";
	 * }
	 * ```
	 */
	class Servers implements \ArrayAccess, \Countable, \IteratorAggregate {

		/**
		 * @var array<string, Server> Cached Server models keyed by server ID (string).
		 */
		private array $servers = [];

		/**
		 * Servers constructor.
		 *
		 * @param Sharkord $sharkord Reference to the main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {}

		/**
		 * Adds or merges a server into the collection.
		 *
		 * @internal
		 * @param array $raw The raw server data from the server.
		 * @return void
		 */
		public function add(array $raw): void {

			if (!isset($raw['serverId'])) {
				$this->sharkord->logger->warning("Cannot cache server: missing 'serverId' in data.");
				return;
			}

			$id = (string) $raw['serverId'];

			if (isset($this->servers[$id])) {
				$this->servers[$id]->updateFromArray($raw);
				return;
			}

			$this->servers[$id] = Server::fromArray($raw, $this->sharkord);

		}

		/**
		 * Merges new data into an already-cached server.
		 *
		 * @internal
		 * @param array $raw The raw server data from the server.
		 * @return Server|null The updated Server model, or null if not cached.
		 */
		public function update(array $raw): ?Server {

			if (!isset($raw['serverId'])) {
				$this->sharkord->logger->warning("Cannot update cached server: missing 'serverId' in data.");
				return null;
			}

			$id = (string) $raw['serverId'];

			if (!isset($this->servers[$id])) {
				return null;
			}

			$this->servers[$id]->updateFromArray($raw);

			return $this->servers[$id];

		}

		/**
		 * Removes a server from the collection.
		 *
		 * @internal
		 * @param string $id The server ID.
		 * @return void
		 */
		public function remove(string $id): void {

			unset($this->servers[$id]);

		}

		/**
		 * Retrieves a server by ID.
		 *
		 * @param string $id The server ID.
		 * @return Server|null
		 */
		public function get(string $id): ?Server {

			return $this->servers[$id] ?? null;

		}

		/**
		 * Returns the first (or only) server in the collection.
		 * Perfect for single-server bots.
		 *
		 * @return Server|null
		 */
		public function getFirst(): ?Server {

			return empty($this->servers) ? null : reset($this->servers);

		}

		// --- ArrayAccess ---

		public function offsetExists(mixed $offset): bool {

			return isset($this->servers[(string) $offset]);

		}

		public function offsetGet(mixed $offset): ?Server {

			return $this->servers[(string) $offset] ?? null;

		}

		/** @throws \LogicException */
		public function offsetSet(mixed $offset, mixed $value): void {

			throw new \LogicException('Servers is read-only. Use add() to cache a server.');

		}

		/** @throws \LogicException */
		public function offsetUnset(mixed $offset): void {

			throw new \LogicException('Servers is read-only. Use remove() to evict a server.');

		}

		// --- Countable ---

		public function count(): int {

			return count($this->servers);

		}

		// --- IteratorAggregate ---

		/**
		 * @return \ArrayIterator<string, Server>
		 */
		public function getIterator(): \ArrayIterator {

			return new \ArrayIterator($this->servers);

		}

	}
	
?>