<?php

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
		 * Updates the user's status.
		 *
		 * @param string $status The new status.
		 * @return void
		 */
		public function updateStatus(string $status): void {

			$this->status = $status;

		}

		/**
		 * Updates the user's name.
		 *
		 * @param string $name The new name.
		 * @return void
		 */
		public function update(string $name, bool $banned, array $roleIds): void {

			$this->name = $name;
			$this->banned = $banned;
			$this->roleIds = $roleIds;

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