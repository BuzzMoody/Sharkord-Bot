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
		 * @param Sharkord         $bot   The main bot instance.
		 * @param array<int, Role> $roles Cache of Role models.
		 */
		public function __construct(
			private Sharkord $bot,
			private array $roles = []
		) {}

		/**
		 * Handles the creation of a roles (or hydration from initial cache).
		 *
		 * @param array $raw The raw role data.
		 * @return void
		 */
		public function handleCreate(array $raw): void {
			
			$role = Role::fromArray($raw, $this->bot);
			
			$this->roles[$raw['id']] = $role;
			$this->bot->logger->info("Role cached: {$role->name}");
			
		}

		/**
		 * Handles updates to a role.
		 *
		 * @param array $raw The raw role data.
		 * @return void
		 */
		public function handleUpdate(array $raw): void {
			
			if (isset($this->roles[$raw['id']])) {
				
				$this->roles[$raw['id']]->updateFromArray($raw);
				$this->bot->logger->info("Role updated: {$this->roles[$raw['id']]->name}");
				
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