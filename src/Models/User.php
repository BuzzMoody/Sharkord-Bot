<?php

	declare(strict_types=1);

	namespace Sharkord\Models;
	
	use Sharkord\Sharkord;
	use Sharkord\Permission;
	use React\Promise\PromiseInterface;
	use function React\Promise\reject;

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
		 * Determine if the user possesses a specific permission through any of their roles.
		 *
		 * @param Permission $permission The permission enum case to check.
		 * @return bool True if any of the user's roles have the permission, false otherwise.
		 */
		public function hasPermission(Permission $permission): bool {
			
			$permissions = $this->permissions;		
			return in_array($permission->value, $permissions, true);
			
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

			if ($roles && in_array($roleId, $roles, false)) {
				
				return true;
				
			}

			// If we checked all roles and didn't find the permission, return false
			return false;
		}
		
		/**
		 * Bans a user from the server.
		 *
		 * @param string $reason The reason for the ban.
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function ban(string $reason = 'No reason given.'): PromiseInterface {
			
			if (!$this->sharkord->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}
			
			if (!$this->sharkord->bot->hasPermission(Permission::MANAGE_USERS)) {
				return reject(new \RuntimeException("Missing MANAGE_USERS permission to ban {$this->name}."));
			}
			
			if ($this->isOwner()) { 
				return reject(new \RuntimeException("Cannot ban {$this->name} as they are the server owner."));
			}
			
			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => ["userId" => $this->id, "reason" => $reason], 
				"path" => "users.ban"
			]);
			
		}

		/**
		 * Unbans a user from the server.
		 *
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function unban(): PromiseInterface {
			
			if (!$this->sharkord->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}
			
			if (!$this->sharkord->bot->hasPermission(Permission::MANAGE_USERS)) {
				return reject(new \RuntimeException("Missing MANAGE_USERS permission to unban {$this->name}."));
			}

			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => ["userId" => $this->id], 
				"path" => "users.unban"
			]);
			
		}
		
		/**
		 * Kicks a user from the server.
		 *
		 * @param string $reason The reason for the kick.
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function kick(string $reason = 'No reason given.'): PromiseInterface {
			
			if (!$this->sharkord->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}
			
			if (!$this->sharkord->bot->hasPermission(Permission::MANAGE_USERS)) {
				return reject(new \RuntimeException("Missing MANAGE_USERS permission to kick {$this->name}."));
			}
			
			if ($this->isOwner()) { 
				return reject(new \RuntimeException("Cannot kick {$this->name} as they are the server owner."));
			}
			
			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => ["userId" => $this->id, "reason" => $reason], 
				"path" => "users.kick"
			]);
			
		}
		
		/**
		 * Deletes a user from the server.
		 *
		 * @param bool  $wipe Whether to delete all associated user data (posts, files, emoji, etc.).
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function delete(bool $wipe = false): PromiseInterface {
			
			if (!$this->sharkord->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}
			
			if (!$this->sharkord->bot->hasPermission(Permission::MANAGE_USERS)) {
				return reject(new \RuntimeException("Missing MANAGE_USERS permission to delete {$this->name}."));
			}
			
			if ($this->isOwner()) { 
				return reject(new \RuntimeException("Cannot delete {$this->name} as they are the server owner."));
			}
			
			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => ["userId" => $this->id, "wipe" => $wipe], 
				"path" => "users.delete"
			]);
			
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
			
			if ($name === 'permissions' && $this->sharkord) {
				
				$permissions = [];
				$roleIds = $this->attributes['roleIds'] ?? [];
				foreach ($roleIds as $roleId) {
					if ($role = $this->sharkord->roles->get($roleId)) {
						$permissions = array_merge($permissions, $role->permissions ?? []);
					}
				}
				return $permissions;
				
			}
			
			// If it's not 'roles', look inside our magic backpack!
			return $this->attributes[$name] ?? null;
			
		}

	}
	
?>