<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Permission;
	use Sharkord\Internal\GuardedAsync;
	use Sharkord\Collections\Roles as RolesCollection;
	use Sharkord\Models\Role;
	use React\Promise\PromiseInterface;

	/**
	 * Class RoleManager
	 *
	 * Manages role lifecycle events and exposes actions for creating roles.
	 * Delegates all cache storage to a Roles collection instance.
	 *
	 * Accessible via `$sharkord->roles`.
	 *
	 * @package Sharkord\Managers
	 *
	 * @example
	 * ```php
	 * // Create a new role and then edit it
	 * $sharkord->roles->add()->then(function(\Sharkord\Models\Role $role) {
	 *     return $role->edit(
	 *         'Moderators',
	 *         '#00aaff',
	 *         \Sharkord\Permission::MANAGE_MESSAGES,
	 *         \Sharkord\Permission::PIN_MESSAGES,
	 *     );
	 * });
	 *
	 * // Set a role as the server default
	 * $sharkord->roles->get(3)?->setAsDefault();
	 *
	 * // Delete a role
	 * $sharkord->roles->get(5)?->delete();
	 *
	 * $sharkord->on(\Sharkord\Events::ROLE_CREATE, function(\Sharkord\Models\Role $role): void {
	 *     echo "New role created: {$role->name}\n";
	 * });
	 * ```
	 */
	class RoleManager {

		use GuardedAsync;

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

		// -------------------------------------------------------------------------
		// Internal event handlers
		// -------------------------------------------------------------------------

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

		// -------------------------------------------------------------------------
		// Public API
		// -------------------------------------------------------------------------

		/**
		 * Creates a new role on the server with default settings.
		 *
		 * Sends the roles.add mutation, then fetches all roles via roles.getAll to
		 * hydrate the new role into the local cache. The returned Role model is ready
		 * for use and can be immediately chained with edit() to set the name, colour,
		 * and permissions.
		 *
		 * Requires the MANAGE_ROLES permission.
		 *
		 * @return PromiseInterface Resolves with the new Role model, rejects on failure.
		 *
		 * @example
		 * ```php
		 * $sharkord->roles->add()->then(function(\Sharkord\Models\Role $role) {
		 *     return $role->edit(
		 *         'Support',
		 *         '#9b59b6',
		 *         \Sharkord\Permission::SEND_MESSAGES,
		 *         \Sharkord\Permission::REACT_TO_MESSAGES,
		 *     );
		 * })->then(function() {
		 *     echo "Role created and configured.\n";
		 * });
		 * ```
		 */
		public function add(): PromiseInterface {

			return $this->guardedAsync(function () {

				$this->sharkord->guard->requirePermission(Permission::MANAGE_ROLES);

				return $this->sharkord->gateway->sendRpc("mutation", [
					"path" => "roles.add",
				])->then(function (array $response) {

					$roleId = $response['data']
						?? throw new \RuntimeException(
							"roles.add response missing 'data' (expected new role ID)."
						);

					return $this->sharkord->gateway->sendRpc("query", [
						"path" => "roles.getAll",
					])->then(function (array $response) use ($roleId) {

						$rawRoles = $response['data']
							?? throw new \RuntimeException(
								"roles.getAll response missing 'data'."
							);

						foreach ($rawRoles as $raw) {
							$this->cache->add($raw);
						}

						return $this->cache->get((int) $roleId)
							?? throw new \RuntimeException(
								"Role ID {$roleId} was not found in cache after add()."
							);

					});

				});

			});

		}

		/**
		 * Retrieves a cached role by ID.
		 *
		 * @param int|string $id The role ID.
		 * @return Role|null The cached Role model, or null if not found.
		 *
		 * @example
		 * ```php
		 * $role = $sharkord->roles->get(3);
		 *
		 * if ($role) {
		 *     echo "Role: {$role->name}\n";
		 * }
		 * ```
		 */
		public function get(int|string $id): ?Role {

			return $this->cache->get($id);

		}

		/**
		 * Re-fetches all roles from the server and updates the local cache in place.
		 *
		 * Existing Role models held by the caller remain valid — each is updated via
		 * updateFromArray() rather than replaced. New roles not previously in cache
		 * are added automatically.
		 *
		 * @return PromiseInterface Resolves with an array<string, Role> keyed by role ID, rejects on failure.
		 *
		 * @example
		 * ```php
		 * $sharkord->roles->fetch()->then(function(array $roles) {
		 *     foreach ($roles as $id => $role) {
		 *         echo "{$id}: {$role->name}\n";
		 *     }
		 * });
		 * ```
		 */
		public function fetch(): PromiseInterface {

			return $this->sharkord->gateway->sendRpc("query", [
				"path" => "roles.getAll",
			])->then(function (array $response) {

				$rawRoles = $response['data']
					?? throw new \RuntimeException("roles.getAll response missing 'data'.");

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