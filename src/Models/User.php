<?php

	declare(strict_types=1);

	namespace Sharkord\Models;
	
	use Sharkord\Sharkord;

	/**
	 * Class User
	 *
	 * Represents a user entity on the server.
	 *
	 * @property-read array $roles The roles assigned to this user.
	 * @package Sharkord\Models
	 */
	class User {

		/**
		 * User constructor.
		 *
		 * @param int           $id      The unique user ID.
		 * @param string        $name    The user's display name.
		 * @param string        $status  The user's online status.
		 * @param array         $roleIds Array of role IDs assigned to the user.
		 * @param Sharkord|null $bot     Reference to the bot instance.
		 */
		public function __construct(
			public int $id,
			public string $name,
			public string $status,
			public bool $banned,
			public array $roleIds = [],
			private ?Sharkord $bot = null
		) {}
		
		/**
		 * Factory method to create a User from raw API data.
		 */
		public static function fromArray(array $raw, ?Sharkord $bot = null): self {
			return new self(
				$raw['id'],
				$raw['name'],
				$raw['status'] ?? 'offline',
				$raw['banned'],
				$raw['roleIds'] ?? [],
				$bot
			);
		}

		/**
		 * Updates the user's information.
		 *
		 * @param array $raw The raw user data from the server.
		 * @return void
		 */
		public function updateFromArray(array $raw): void {
			
			if (isset($raw['name'])) $this->name = $raw['name'];
			if (isset($raw['status'])) $this->status = $raw['status'];
			if (isset($raw['banned'])) $this->banned = $raw['banned'];
			if (isset($raw['roleIds'])) $this->roleIds = $raw['roleIds'];
			
		}
		
		/**
		 * Updates the user's status.
		 *
		 * @param string $status The new status.
		 * @return void
		 */
		public function updateStatus(string $status): void {

			$this->status = $status;

		}
		
		/**
		 * Checks if the user has a specific permission via their assigned roles.
		 *
		 * @param string $permission The permission string to check.
		 * @return bool True if the user has the permission, false otherwise.
		 */
		public function hasPermission(string $permission): bool {
			// Get all the Role objects for this user using the magic getter
			$roles = $this->roles;

			if ($roles) {
				foreach ($roles as $role) {
					// If any of their roles has the permission, return true immediately
					if ($role->hasPermission($permission)) {
						return true;
					}
				}
			}

			// If we checked all roles and didn't find the permission, return false
			return false;
		}

		/**
		 * Magic getter to access Roles.
		 *
		 * @param string $name Property name.
		 * @return mixed
		 */
		public function __get(string $name): mixed {
			
			if ($name === 'roles' && $this->bot) {
				$roles = [];
				foreach ($this->roleIds as $roleId) {
					if ($role = $this->bot->roles->get($roleId)) {
						$roles[] = $role;
					}
				}
				return $roles;
			}
			return null;
			
		}

	}
	
?>