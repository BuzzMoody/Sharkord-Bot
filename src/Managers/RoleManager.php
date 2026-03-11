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
		 * Returns the underlying Roles collection.
		 *
		 * @return RolesCollection
		 */
		public function collection(): RolesCollection {

			return $this->cache;

		}

	}
	
?>