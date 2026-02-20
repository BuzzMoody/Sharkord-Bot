<?php

	declare(strict_types=1);

	namespace Sharkord\Models;
	
	use Sharkord\Sharkord;

	/**
	 * Class Role
	 *
	 * Represents a user role (permissions, color, etc).
	 *
	 * @package Sharkord\Models
	 */
	class Role {

		/**
		 * Role constructor.
		 *
		 * @param int    $id          The unique role ID.
		 * @param string $name        The role name.
		 * @param string $color       The hex color code.
		 * @param array  $permissions List of permission strings.
		 * @param bool   $isDefault   Whether this is the default role.
		 * @param int    $position    The sort position.
		 */
		public function __construct(
			public int $id,
			public string $name,
			public string $color,
			public array $permissions = [],
			public bool $isDefault = false,
			public int $position = 0,
			private Sharkord $sharkord
		) {}
		
		/**
		 * Factory method to create a Role from raw API data.
		 */
		public static function fromArray(array $raw, Sharkord $sharkord): self {
			return new self(
				$raw['id'], 
				$raw['name'], 
				$raw['color'], 
				$raw['permissions'] ?? [], 
				$raw['isDefault'] ?? false,
				$raw['position'] ?? 0,
				$sharkord
			);
		}
		
		/**
		 * Updates the Role's information.
		 *
		 * @param array $raw The raw Role data from the server.
		 * @return void
		 */
		public function updateFromArray(array $raw): void {
			
			if (isset($raw['name'])) $this->name = $raw['name'];
			if (isset($raw['color'])) $this->color = $raw['color'];
			if (isset($raw['permissions'])) $this->permissions = $raw['permissions'];
			if (isset($raw['isDefault'])) $this->isDefault = $raw['isDefault']; 
			if (isset($raw['position'])) $this->position = $raw['position'];
			
		}
		
		/**
		 * Checks if this Role has a specific permission.
		 *
		 * @param string $permission The permission string to check.
		 * @return bool True if the Role has the permission, false otherwise.
		 */
		public function hasPermission(string $permission): bool {
			
			return in_array($permission, $this->permissions, true);
			
		}

	}
?>