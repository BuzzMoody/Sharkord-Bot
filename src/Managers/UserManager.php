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
		 * @param array<int, User> Cache of User models indexed by ID.
		 */
		public function __construct(
			private Sharkord $bot,
			private array $users = []
		) {}

		/**
		 * Handles the creation of a user (or hydration from initial cache).
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		public function handleCreate(array $raw): void {
			
			// Default status to offline if not provided
			$status = $raw['status'] ?? 'offline';
			
			$user = new User($raw['id'], $raw['name'], $status, $raw['roleIds'] ?? []);
			$this->users[$raw['id']] = $user;
			
			$this->bot->logger->info("User cached: {$user->name} ({$user->id})");
			
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
				$this->bot->logger->info("User came online: {$raw['id']}");
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
				$this->bot->logger->info("User went offline: {$this->users[$id]->name}");
			}
			
		}

		/**
		 * Handles updates to user details (e.g., name change).
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		public function handleUpdate(array $raw): void {
			
			if (isset($this->users[$raw['id']])) {
				$oldName = $this->users[$raw['id']]->name;
				$this->users[$raw['id']]->updateName($raw['name']);
				$this->bot->logger->info("User changed their name from {$oldName} to {$this->users[$raw['id']]->name}");
			}
			
		}
		
		/**
		 * Retrieves a user by ID.
		 *
		 * @param int $id The user ID.
		 * @return User|null Returns the User object or null if not found.
		 */
		public function get(int $id): ?User {
			
			return $this->users[$id] ?? null;
			
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