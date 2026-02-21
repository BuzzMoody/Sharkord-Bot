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
		 * @var array Stores all dynamic role data from the API
		 */
		private array $attributes = [];

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
		 * Checks if this Role has a specific permission.
		 *
		 * @param string $permission The permission string to check.
		 * @return bool True if the Role has the permission, false otherwise.
		 */
		public function hasPermission(string $permission): bool {
			
			// Safely grab the permissions array from our attributes, or use an empty array if none exist
			$permissions = $this->attributes['permissions'] ?? [];
			return in_array($permission, $permissions, true);
			
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