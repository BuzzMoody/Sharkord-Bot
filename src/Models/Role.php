<?php

	declare(strict_types=1);

	namespace Sharkord\Models;
	
	use Sharkord\Sharkord;
	use Sharkord\Permission;

	/**
	 * Class Role
	 *
	 * Represents a user role (permissions, color, etc).
	 *
	 * @package Sharkord\Models
	 */
	class Role {

		/**
		 * @var array Stores all dynamic role data from the API
		 */
		private array $attributes = [];
		
		/**
		 * Array of permissions assigned to this role (stored as strings).
		 * @var array<string>
		 */
		protected array $permissions = [];

		/**
		 * Role constructor.
		 *
		 * @param Sharkord $sharkord Reference to the main bot instance.
		 * @param array    $rawData  The raw array of data from the API.
		 */
		public function __construct(
			private Sharkord $sharkord,
			array $rawData
		) {
			$this->updateFromArray($rawData);
		}
		
		/**
		 * Factory method to create a Role from raw API data.
		 */
		public static function fromArray(array $raw, Sharkord $sharkord): self {
			return new self($sharkord, $raw);
		}
		
		/**
		 * Updates the Role's information dynamically.
		 *
		 * @param array $raw The raw Role data from the server.
		 * @return void
		 */
		public function updateFromArray(array $raw): void {
			
			// Merge the new data into our attributes array
			$this->attributes = array_merge($this->attributes, $raw);
			
		}
		
		/**
		 * Determine if the role possesses a specific permission.
		 *
		 * @param Permission $permission The permission enum case to check.
		 * @return bool True if the role has the permission, false otherwise.
		 */
		public function hasPermission(Permission $permission): bool {
			
			print_r($this->permissions);
			return in_array($permission->value, $this->permissions, true);
			
		}
		
		/**
		 * Returns all the attributes as an array. Perfect for debugging!
		 *
		 * @return array
		 */
		public function toArray(): array {
			
			return $this->attributes;
			
		}

		/**
		 * Magic getter. This is triggered whenever you try to access a property 
		 * that isn't explicitly defined (e.g., $role->name or $role->color).
		 *
		 * @param string $name Property name.
		 * @return mixed
		 */
		public function __get(string $name): mixed {
			
			return $this->attributes[$name] ?? null;
			
		}

	}
?>