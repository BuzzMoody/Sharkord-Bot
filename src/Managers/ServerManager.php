<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Collections\Servers as ServersCollection;
	use Sharkord\Models\Server;

	/**
	 * Class ServerManager
	 *
	 * Manages server lifecycle events, delegating all cache storage to a
	 * Servers collection instance.
	 *
	 * Accessible via `$sharkord->servers`.
	 *
	 * @package Sharkord\Managers
	 *
	 * @example
	 * ```php
	 * // Most bots only operate in one server — getFirst() is the common pattern.
	 * $server = $sharkord->servers->getFirst();
	 * echo "Connected to: {$server->name}\n";
	 *
	 * $sharkord->on(\Sharkord\Events::SERVER_UPDATE, function(\Sharkord\Models\Server $server): void {
	 *     echo "Server settings updated. Name is now: {$server->name}\n";
	 * });
	 * ```
	 */
	class ServerManager {

		private ServersCollection $cache;

		/**
		 * ServerManager constructor.
		 *
		 * @param Sharkord $sharkord The main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {
			$this->cache = new ServersCollection($this->sharkord);
		}

		/**
		 * Handles creating or updating a server in the cache from the initial join payload.
		 *
		 * @internal
		 * @param array $raw The raw server data.
		 * @return void
		 */
		public function hydrate(array $raw): void {

			$this->cache->add($raw);

		}

		/**
		 * Handles a server-pushed update to the server's public settings.
		 *
		 * Updates the cached model in place and emits a `serverupdate` event.
		 *
		 * @internal
		 * @param array $raw The raw server data.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::SERVER_UPDATE, function(\Sharkord\Models\Server $server): void {
		 *     echo "Server name is now: {$server->name}\n";
		 * });
		 * ```
		 */
		public function onUpdate(array $raw): void {

			$this->cache->update($raw);

		}

		/**
		 * Handles the server-initiated removal of a server (e.g. bot kicked).
		 *
		 * Removes the server from the local cache.
		 *
		 * @internal
		 * @param string $serverId The ID of the deleted server.
		 * @return void
		 */
		public function onDelete(string $serverId): void {

			$this->cache->remove($serverId);

		}

		/**
		 * Retrieves the first (or only) server in the cache.
		 *
		 * The preferred pattern for single-server bots.
		 *
		 * @return Server|null The first cached Server model, or null if the cache is empty.
		 *
		 * @example
		 * ```php
		 * $server = $sharkord->servers->getFirst();
		 *
		 * if ($server) {
		 *     echo "Connected to: {$server->name}\n";
		 * }
		 * ```
		 */
		public function getFirst(): ?Server {

			return $this->cache->getFirst();

		}

		/**
		 * Retrieves a server by ID.
		 *
		 * @param string $serverId The server ID.
		 * @return Server|null The cached Server model, or null if not found.
		 *
		 * @example
		 * ```php
		 * $server = $sharkord->servers->get('abc123');
		 *
		 * if ($server) {
		 *     echo "Server: {$server->name}\n";
		 * }
		 * ```
		 */
		public function get(string $serverId): ?Server {

			return $this->cache->get($serverId);

		}

		/**
		 * Returns the underlying Servers collection.
		 *
		 * @return ServersCollection
		 *
		 * @example
		 * ```php
		 * foreach ($sharkord->servers->collection() as $id => $server) {
		 *     echo "{$id}: {$server->name}\n";
		 * }
		 * ```
		 */
		public function collection(): ServersCollection {

			return $this->cache;

		}

	}

?>