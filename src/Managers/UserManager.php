<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Models\User;

	/**
	 * Class UserManager
	 *
	 * Manages the state, creation, updating, and status of users.
	 *
	 * @package Sharkord\Managers
	 */
	class UserManager {
		
		/**
		 * ChannelManager constructor.
		 *
		 * @param Sharkord         $sharkord   The main bot instance.
		 * @param array<int, User> $users Cache of User models indexed by ID.
		 */
		public function __construct(
			private Sharkord $sharkord,
			private array $users = []
		) {}

		/**
		 * Handles the hydration of a user.
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		public function hydrate(array $raw): void {
			
			$user = User::fromArray($raw, $this->sharkord);
			$this->users[$raw['id']] = $user;
			
		}

		/**
		 * Handles the creation of a user.
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		public function create(array $raw): void {
			
			$user = User::fromArray($raw, $this->sharkord);
			$this->users[$raw['id']] = $user;
			
			$this->sharkord->emit('usercreate', [$user]);
			
		}

		/**
		 * Handles a user joining the server (sets status to online).
		 *
		 * @param array $raw The raw user data (usually just ID here).
		 * @return void
		 */
		public function join(array $raw): void {
			
			if (isset($this->users[$raw['id']])) {
				
				$this->users[$raw['id']]->updateStatus('online');
				
				$this->sharkord->emit('userjoin', [$this->users[$raw['id']]]);
				
			}
			
		}

		/**
		 * Handles a user leaving the server (sets status to offline).
		 *
		 * @param int $id The ID of the user leaving.
		 * @return void
		 */
		public function leave(int $id): void {
			
			if (isset($this->users[$id])) {
				
				$this->users[$id]->updateStatus('offline');
				
				$this->sharkord->emit('userleave', [$this->users[$id]]);
				
			}
			
		}

		/**
		 * Handles updates to user details (e.g., name change, ban, roles).
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		public function update(array $raw): void {
			
			if (!isset($this->users[$raw['id']])) {
				return;
			}

			$user = $this->users[$raw['id']];

			$nameChanged = $user->name !== $raw['name'];
			$banChanged  = array_key_exists('banned', $raw) && $user->banned !== $raw['banned'];
			$gotBanned   = $banChanged && (bool)$raw['banned'];
			$gotUnbanned = $banChanged && !(bool)$raw['banned'];

			$user->updateFromArray($raw);

			if ($nameChanged) {
				$this->sharkord->emit('namechange', [$user]);
			}
			if ($gotBanned) {
				$this->sharkord->emit('ban', [$user]);
			} 
			if ($gotUnbanned) {
				$this->sharkord->emit('unban', [$user]);
			}
			
		}
		
		/**
		 * Handles user deletion.
		 *
		 * @param int $id The ID of the deleted user.
		 * @return void
		 */
		 public function delete(int $id): void {
			
			if (!isset($this->users[$id])) { 
				$this->sharkord->logger->error("User ID {$id} doesn't exist, therefore cannot be deleted.");
				return;
			}
			
			$this->sharkord->emit('userdelete', [$this->users[$id]]);
			
			unset($this->users[$id]);
			
		}
		
		/**
		 * Retrieves a user by ID or name.
		 *
		 * @param int|string $identifier The user ID or name.
		 * @return User|null Returns the User object or null if not found.
		 */
		public function get(int|string $identifier): ?User {
			
			if (is_int($identifier) || (is_string($identifier) && ctype_digit($identifier))) {
				
				return $this->users[(int)$identifier] ?? null;
				
			}
			
			foreach ($this->users as $user) {
				
				if ($user->name === $identifier) {
					
					return $user;
					
				}
				
			}
			
			return null;
			
		}
		
		/**
		 * Returns the count of cached users.
		 * * @return int
		 */
		public function count(): int {
			
			return count($this->users);
			
		}

	}
	
?>