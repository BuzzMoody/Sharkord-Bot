<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Collections\Roles as RolesCollection;
	use Sharkord\Models\Role;

	/**
	 * Class RoleManager
	 *
	 * Manages role lifecycle events, delegating all cache storage to a
	 * Roles collection instance.
	 *
	 * @package Sharkord\Managers
	 */
	class RoleManager {

		private RolesCollection $cache;

		/**
		 * RoleManager constructor.
		 *
		 * @param Sharkord $sharkord The main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {
			$this->cache = new RolesCollection($this->sharkord);
		}

		/**
		 * Handles the hydration of a role.
		 *
		 * @param array $raw The raw role data.
		 * @return void
		 */
		public function hydrate(array $raw): void {

			$this->cache->add($raw);

		}

		/**
		 * Handles the creation of a role.
		 *
		 * @param array $raw The raw role data.
		 * @return void
		 */
		public function create(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot create role: missing 'id' in data.");
				return;
			}

			$this->cache->add($raw);

			$this->sharkord->emit('rolecreate', [$this->cache->get($raw['id'])]);

		}

		/**
		 * Handles updates to a role.
		 *
		 * @param array $raw The raw role data.
		 * @return void
		 */
		public function update(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot update role: missing 'id' in data.");
				return;
			}

			$role = $this->cache->update($raw);

			if ($role) {
				$this->sharkord->emit('roleupdate', [$role]);
			}

		}

		/**
		 * Handles role deletion.
		 *
		 * @param int $id The ID of the deleted role.
		 * @return void
		 */
		public function delete(int $id): void {

			$role = $this->cache->get($id);

			if (!$role) {
				$this->sharkord->logger->error("Role ID {$id} doesn't exist, therefore cannot be deleted.");
				return;
			}

			$this->sharkord->emit('roledelete', [$role]);
			$this->cache->remove($id);

		}

		/**
		 * Retrieves a role by ID.
		 *
		 * @param int $id The role ID.
		 * @return Role|null
		 */
		public function get(int $id): ?Role {

			return $this->cache->get($id);

		}
		
		/**
		 * Re-fetches all roles from the server and updates the local cache in place.
		 *
		 * Existing Role models held by the caller remain valid — each is updated via
		 * updateFromArray() rather than replaced. New roles not previously in cache
		 * are added automatically.
		 *
		 * @return PromiseInterface Resolves with an array of all cached Role models, rejects on failure.
		 *
		 * @example
		 * ```php
		 * $sharkord->roles->fetch()->then(function(array $roles) {
		 *     foreach ($roles as $role) {
		 *         echo "{$role->name}\n";
		 *     }
		 * });
		 * ```
		 */
		public function fetch(): PromiseInterface {

			return $this->sharkord->gateway->sendRpc("query", [
				"path" => "roles.getAll",
			])->then(function (array $response) {

				$raw = $response['data']
					?? throw new \RuntimeException("roles.getAll response missing 'data'.");

				foreach ($raw as $rawRole) {
					$this->hydrate($rawRole);
				}

				return iterator_to_array($this->cache);

			});

		}

		/**
		 * Returns the underlying Roles collection.
		 *
		 * @return RolesCollection
		 */
		public function collection(): RolesCollection {

			return $this->cache;

		}

	}
	
?>