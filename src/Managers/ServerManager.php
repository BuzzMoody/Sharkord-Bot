<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Models\Server;

	/**
	 * Class ServerManager
	 *
	 * Manages the state, creation, updating, and deletion of servers.
	 *
	 * @package Sharkord\Managers
	 */
	class ServerManager {

		/**
		 * ServerManager constructor.
		 *
		 * @param Sharkord            $sharkord The main bot instance.
		 * @param array<string, Server> $servers  Cache of Server models, keyed by serverId.
		 */
		public function __construct(
			private Sharkord $sharkord,
			private array $servers = []
		) {}

		/**
		 * Handles creating or updating a server in the cache.
		 *
		 * @param array $raw The raw server data.
		 * @return void
		 */
		public function handleCreate(array $raw): void {
			
			// Check if the serverId exists in the payload to avoid errors
			if (!isset($raw['serverId'])) return;

			$server = Server::fromArray($raw, $this->sharkord);
			$this->servers[$raw['serverId']] = $server;
			
		}

		/**
		 * Handles updates to a server.
		 *
		 * @param array $raw The raw server data.
		 * @return void
		 */
		public function handleUpdate(array $raw): void {
			
			if (isset($raw['serverId']) && isset($this->servers[$raw['serverId']])) {
				$server = $this->servers[$raw['serverId']];
				$server->updateFromArray($raw);
			}
			
		}

		/**
		 * Handles server deletion (e.g., bot gets kicked from a server).
		 *
		 * @param string $serverId The ID of the deleted server.
		 * @return void
		 */
		public function handleDelete(string $serverId): void {
			
			unset($this->servers[$serverId]);
			
		}
		
		/**
		 * Retrieves the first (or only) server in the cache.
		 * Perfect for single-server bots.
		 *
		 * @return Server|null
		 */
		public function getFirst(): ?Server {
			
			return empty($this->servers) ? null : reset($this->servers);
			
		}

		/**
		 * Retrieves a server by ID.
		 *
		 * @param string $serverId The server ID.
		 * @return Server|null
		 */
		public function get(string $serverId): ?Server {
			
			return $this->servers[$serverId] ?? null;
			
		}

	}
?>