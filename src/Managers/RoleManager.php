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
		 * @param Sharkord         $sharkord   The main bot instance.
		 * @param array<int, Role> $roles Cache of Role models.
		 */
		public function __construct(
			private Sharkord $sharkord,
			private array $roles = []
		) {}
		
		/**
		 * Handles the hydration of a role.
		 *
		 * @param array $raw The raw role data.
		 * @return void
		 */
		public function hydrate(array $raw): void {
			
			$role = Role::fromArray($raw, $this->sharkord);
			$this->roles[$raw['id']] = $role;
			
		}

		/**
		 * Handles the creation of a roles.
		 *
		 * @param array $raw The raw role data.
		 * @return void
		 */
		public function create(array $raw): void {
			
			$role = Role::fromArray($raw, $this->sharkord);
			$this->roles[$raw['id']] = $role;
			
			$this->sharkord->emit('rolecreate', [$role]);
			
		}

		/**
		 * Handles updates to a role.
		 *
		 * @param array $raw The raw role data.
		 * @return void
		 */
		public function update(array $raw): void {
			
			if (isset($this->roles[$raw['id']])) {
				
				$this->roles[$raw['id']]->updateFromArray($raw);
				
				$this->sharkord->emit('roleupdate', [$this->roles[$raw['id']]]);
				
			}
			
		}

		/**
		 * Handles role deletion.
		 *
		 * @param int $id The ID of the deleted role.
		 * @return void
		 */
		public function delete(int $id): void {
			
			if (!isset($this->roles[$id])) { 
				$this->sharkord->logger->error("Role ID {$id} doesn't exist, therefore cannot be deleted.");
				return;
			}
			
			$this->sharkord->emit('roledelete', [$this->roles[$id]]);
			
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