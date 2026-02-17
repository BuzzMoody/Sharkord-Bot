<?php

	namespace Sharkord\Models;

	/**
	 * Class User
	 *
	 * Represents a user entity on the server.
	 *
	 * @package Sharkord\Models
	 */
	class User {

		/**
		 * User constructor.
		 *
		 * @param int    $id      The unique user ID.
		 * @param string $name    The user's display name.
		 * @param string $status  The user's online status (e.g., 'online', 'offline').
		 * @param array  $roleIds Array of role IDs assigned to the user.
		 */
		public function __construct(
			public int $id,
			public string $name,
			public string $status,
			public array $roleIds = []
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
		public function updateName(string $name): void {

			$this->name = $name;

		}

	}

?>