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
		 * @var array Stores all dynamic user data from the API
		 */
		private array $attributes = [];

		/**
		 * User constructor.
		 *
		 * @param Sharkord $sharkord Reference to the bot instance.
		 * @param array    $rawData  The raw array of data from the API.
		 */
		public function __construct(
			private Sharkord $sharkord,
			array $rawData
		) {
			$this->updateFromArray($rawData);
		}
		
		/**
		 * Factory method to create a User from raw API data.
		 */
		public static function fromArray(array $raw, Sharkord $sharkord): self {
			return new self($sharkord, $raw);
		}

		/**
		 * Updates the user's information dynamically.
		 *
		 * @param array $raw The raw user data from the server.
		 * @return void
		 */
		public function updateFromArray(array $raw): void {
			
			// 1. Throw away the heavy items we don't want to store
			unset($raw['avatar'], $raw['banner']);

			// 2. Default the status to offline if it wasn't provided in the payload
			if (!isset($raw['status']) && !isset($this->attributes['status'])) {
				$raw['status'] = 'offline';
			}

			// 3. Merge the new data into our magic backpack (attributes array)
			$this->attributes = array_merge($this->attributes, $raw);
			
		}
		
		/**
		 * Updates the user's status specifically.
		 *
		 * @param string $status The new status.
		 * @return void
		 */
		public function updateStatus(string $status): void {
			$this->attributes['status'] = $status;
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
		 * Checks if the user is a server owner.
		 *
		 * @return bool True if the user is an owner, false otherwise.
		 */
		public function isOwner(): bool {
			
			return $this->hasRole(1);
			
		}
		
		/**
		 * Checks if the user has a specific role via their assigned role ids.
		 *
		 * @param int $roleId The role id to check.
		 * @return bool True if the user has the role, false otherwise.
		 */
		public function hasRole(int $roleId): bool {
			// Get all the Role objects for this user using the magic getter
			$roles = $this->roleIds;

			if ($roles && in_array($roleId, $roles, true)) {
				
				return true;
				
			}

			// If we checked all roles and didn't find the permission, return false
			return false;
		}
		
		/**
		 * Bans this user from the server.
		 *
		 * @param string $reason The reason for the ban.
		 * @return void
		 */
		public function ban(string $reason = 'No reason given.'): void {
			
			// We ask the main bot instance to ban "this" specific user
			$this->sharkord->ban($this, $reason);
			
		}

		/**
		 * Unbans this user from the server.
		 *
		 * @return void
		 */
		public function unban(): void {
			
			// We ask the main bot instance to unban "this" specific user
			$this->sharkord->unban($this);
			
		}
		
		/**
		 * Kicks this user from the server.
		 *
		 * @param string $reason The reason for the kick.
		 * @return void
		 */
		public function kick(string $reason = 'No reason given.'): void {
			
			// We ask the main bot instance to kick "this" specific user
			$this->sharkord->kick($this, $reason);
			
		}
		
		/**
		 * Delete this user from the server.
		 *
		 * @param string $reason The reason for the deletion.
		 * @return void
		 */
		public function delete(bool $wipe = false): void {
			
			// We ask the main bot instance to delete "this" specific user
			$this->sharkord->kick($this, $wipe);
			
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
		 * that isn't explicitly defined (e.g., $user->bio or $user->id).
		 *
		 * @param string $name Property name.
		 * @return mixed
		 */
		public function __get(string $name): mixed {
			
			if ($name === 'server' && $this->sharkord) {
				// We use the bot instance to ask the ServerManager for the server object
				return $this->sharkord->servers->getFirst();
			}
			
			// Handle the special 'roles' request
			if ($name === 'roles' && $this->sharkord) {
				$roles = [];
				$roleIds = $this->attributes['roleIds'] ?? [];
				foreach ($roleIds as $roleId) {
					if ($role = $this->sharkord->roles->get($roleId)) {
						$roles[] = $role;
					}
				}
				return $roles;
			}
			
			// If it's not 'roles', look inside our magic backpack!
			return $this->attributes[$name] ?? null;
			
		}

	}
	
?>