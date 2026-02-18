<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Models\Role;

	/**
	 * Class RoleManager
	 *
	 * Manages the state, creation, and updating of roles.
	 *
	 * @package Sharkord\Managers
	 */
	class RoleManager {

		/**
		 * RoleManager constructor.
		 *
		 * @param Sharkord        $bot   The main bot instance.
		 * @param array<int, Role> $roles Cache of Role models.
		 */
		public function __construct(
			private Sharkord $bot,
			private array $roles = []
		) {}

		/**
		 * Handles creating or updating a role in the cache.
		 *
		 * @param array $raw The raw role data.
		 * @return void
		 */
		public function handleCreate(array $raw): void {
			
			$role = new Role(
				$raw['id'], 
				$raw['name'], 
				$raw['color'], 
				$raw['permissions'] ?? [], 
				$raw['isDefault'] ?? false,
				$raw['position'] ?? 0
			);
			$this->roles[$raw['id']] = $role;
			
		}

		/**
		 * Handles updates to a role.
		 *
		 * @param array $raw The raw role data.
		 * @return void
		 */
		public function handleUpdate(array $raw): void {
			
			if (isset($this->roles[$raw['id']])) {
				$role = $this->roles[$raw['id']];
				$role->name = $raw['name'];
				$role->color = $raw['color'];
				$role->permissions = $raw['permissions'] ?? $role->permissions;
			}
			
		}

		/**
		 * Handles role deletion.
		 *
		 * @param int $id The ID of the deleted role.
		 * @return void
		 */
		public function handleDelete(int $id): void {
			
			unset($this->roles[$id]);
			
		}

		/**
		 * Retrieves a role by ID.
		 *
		 * @param int $id The role ID.
		 * @return Role|null
		 */
		public function get(int $id): ?Role {
			
			return $this->roles[$id] ?? null;
			
		}

	}
?>