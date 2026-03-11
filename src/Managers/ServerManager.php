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
	 * @package Sharkord\Managers
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
		 * Handles creating or updating a server in the cache.
		 *
		 * @param array $raw The raw server data.
		 * @return void
		 */
		public function hydrate(array $raw): void {

			$this->cache->add($raw);

		}

		/**
		 * Handles updates to a server.
		 *
		 * @param array $raw The raw server data.
		 * @return void
		 */
		public function update(array $raw): void {

			$this->cache->update($raw);

		}

		/**
		 * Handles server deletion (e.g., bot gets kicked from a server).
		 *
		 * @param string $serverId The ID of the deleted server.
		 * @return void
		 */
		public function delete(string $serverId): void {

			$this->cache->remove($serverId);

		}

		/**
		 * Retrieves the first (or only) server in the cache.
		 * Perfect for single-server bots.
		 *
		 * @return Server|null
		 */
		public function getFirst(): ?Server {

			return $this->cache->getFirst();

		}

		/**
		 * Retrieves a server by ID.
		 *
		 * @param string $serverId The server ID.
		 * @return Server|null
		 */
		public function get(string $serverId): ?Server {

			return $this->cache->get($serverId);

		}

		/**
		 * Returns the underlying Servers collection.
		 *
		 * @return ServersCollection
		 */
		public function collection(): ServersCollection {

			return $this->cache;

		}

	}
	
?>