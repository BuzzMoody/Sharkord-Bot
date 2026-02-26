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
		 * Handles the creation of a user (or hydration from initial cache).
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		public function handleCreate(array $raw): void {
			
			$user = User::fromArray($raw, $this->sharkord);
			
			$this->users[$raw['id']] = $user;
			
		}

		/**
		 * Handles a user joining the server (sets status to online).
		 *
		 * @param array $raw The raw user data (usually just ID here).
		 * @return void
		 */
		public function handleJoin(array $raw): void {
			
			if (isset($this->users[$raw['id']])) {
				$this->users[$raw['id']]->updateStatus('online');
			}
			
		}

		/**
		 * Handles a user leaving the server (sets status to offline).
		 *
		 * @param int $id The ID of the user leaving.
		 * @return void
		 */
		public function handleLeave(int $id): void {
			
			if (isset($this->users[$id])) {
				$this->users[$id]->updateStatus('offline');
			}
			
		}

		/**
		 * Handles updates to user details (e.g., name change, ban, roles).
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		public function handleUpdate(array $raw): void {
			
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