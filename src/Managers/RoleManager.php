<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Collections\Roles as RolesCollection;
	use Sharkord\Models\Role;
	use React\Promise\PromiseInterface;

	/**
	 * Class RoleManager
	 *
	 * Manages role lifecycle events, delegating all cache storage to a
	 * Roles collection instance.
	 *
	 * Accessible via `$sharkord->roles`.
	 *
	 * @package Sharkord\Managers
	 *
	 * @example
	 * ```php
	 * $sharkord->on(\Sharkord\Events::ROLE_CREATE, function(\Sharkord\Models\Role $role): void {
	 *     echo "New role created: {$role->name}\n";
	 * });
	 *
	 * $role = $sharkord->roles->get(7);
	 *
	 * if ($role) {
	 *     echo "Role name: {$role->name}\n";
	 * }
	 * ```
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
		 * Handles the hydration of a role from the initial join payload.
		 *
		 * @internal
		 * @param array $raw The raw role data.
		 * @return void
		 */
		public function hydrate(array $raw): void {

			$this->cache->add($raw);

		}

		/**
		 * Handles the server-initiated creation of a role.
		 *
		 * Adds the role to the local cache and emits a `rolecreate` event.
		 *
		 * @internal
		 * @param array $raw The raw role data.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::ROLE_CREATE, function(\Sharkord\Models\Role $role): void {
		 *     echo "Role '{$role->name}' was created.\n";
		 * });
		 * ```
		 */
		public function onCreate(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot create role: missing 'id' in data.");
				return;
			}

			$this->cache->add($raw);

			$this->sharkord->emit('rolecreate', [$this->cache->get($raw['id'])]);

		}

		/**
		 * Handles a server-pushed update to a role.
		 *
		 * Updates the cached model in place and emits a `roleupdate` event.
		 *
		 * @internal
		 * @param array $raw The raw role data.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::ROLE_UPDATE, function(\Sharkord\Models\Role $role): void {
		 *     echo "Role '{$role->name}' was updated.\n";
		 * });
		 * ```
		 */
		public function onUpdate(array $raw): void {

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
		 * Handles the server-initiated deletion of a role.
		 *
		 * Emits a `roledelete` event with the cached model before removing it.
		 *
		 * @internal
		 * @param int $id The ID of the deleted role.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::ROLE_DELETE, function(\Sharkord\Models\Role $role): void {
		 *     echo "Role '{$role->name}' was deleted.\n";
		 * });
		 * ```
		 */
		public function onDelete(int $id): void {

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
		 * @return Role|null The cached Role model, or null if not found.
		 *
		 * @example
		 * ```php
		 * $role = $sharkord->roles->get(7);
		 *
		 * if ($role) {
		 *     echo "Role: {$role->name}\n";
		 * }
		 * ```
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
				"path" => "roles.get",
			])->then(function (array $response) {

				$rawRoles = $response['data']
					?? throw new \RuntimeException("roles.get response missing 'data'.");

				foreach ($rawRoles as $raw) {
					$this->cache->add($raw);
				}

				return iterator_to_array($this->cache);

			});

		}

		/**
		 * Returns the underlying Roles collection.
		 *
		 * @return RolesCollection
		 *
		 * @example
		 * ```php
		 * foreach ($sharkord->roles->collection() as $id => $role) {
		 *     echo "{$id}: {$role->name}\n";
		 * }
		 * ```
		 */
		public function collection(): RolesCollection {

			return $this->cache;

		}

	}

?>