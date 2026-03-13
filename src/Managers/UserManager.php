<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Collections\Users as UsersCollection;
	use Sharkord\Models\User;

	/**
	 * Class UserManager
	 *
	 * Manages user lifecycle events, delegating all cache storage to a
	 * Users collection instance.
	 *
	 * @package Sharkord\Managers
	 */
	class UserManager {

		private UsersCollection $cache;

		/**
		 * UserManager constructor.
		 *
		 * @param Sharkord $sharkord The main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {
			$this->cache = new UsersCollection($this->sharkord);
		}

		/**
		 * Handles the hydration of a user.
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		public function hydrate(array $raw): void {

			$this->cache->add($raw);

		}

		/**
		 * Handles the creation of a user.
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		public function create(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot create user: missing 'id' in data.");
				return;
			}

			$this->cache->add($raw);

			$this->sharkord->emit('usercreate', [$this->cache->get($raw['id'])]);

		}

		/**
		 * Handles a user joining the server (sets status to online).
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		public function join(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot process user join: missing 'id' in data.");
				return;
			}

			$user = $this->cache->get($raw['id']);

			if ($user) {
				$user->updateStatus('online');
				$this->sharkord->emit('userjoin', [$user]);
			}

		}

		/**
		 * Handles a user leaving the server (sets status to offline).
		 *
		 * @param int $id The ID of the user leaving.
		 * @return void
		 */
		public function leave(int $id): void {

			$user = $this->cache->get($id);

			if ($user) {
				$user->updateStatus('offline');
				$this->sharkord->emit('userleave', [$user]);
			}

		}

		/**
		 * Handles updates to user details (e.g., name change, ban, roles).
		 *
		 * @param array $raw The raw user data.
		 * @return void
		 */
		public function update(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot update user: missing 'id' in data.");
				return;
			}

			$user = $this->cache->get($raw['id']);

			if (!$user) {
				return;
			}

			$nameChanged = $user->name !== $raw['name'];
			$banChanged  = array_key_exists('banned', $raw) && $user->banned !== $raw['banned'];
			$gotBanned   = $banChanged && (bool) $raw['banned'];
			$gotUnbanned = $banChanged && !(bool) $raw['banned'];

			$this->cache->update($raw);

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

			$user = $this->cache->get($id);

			if (!$user) {
				$this->sharkord->logger->error("User ID {$id} doesn't exist, therefore cannot be deleted.");
				return;
			}

			$this->sharkord->emit('userdelete', [$user]);
			$this->cache->remove($id);

		}

		/**
		 * Retrieves a user by ID or name.
		 *
		 * @param int|string $identifier The user ID or name.
		 * @return User|null
		 */
		public function get(int|string $identifier): ?User {

			return $this->cache->get($identifier);

		}
		
		/**
		 * Re-fetches all users from the server and updates the local cache in place.
		 *
		 * Existing User models held by the caller remain valid — each is updated via
		 * updateFromArray() rather than replaced. New users not previously in cache
		 * are added automatically.
		 *
		 * @return PromiseInterface Resolves with an array of all cached User models, rejects on failure.
		 *
		 * @example
		 * ```php
		 * $sharkord->users->fetch()->then(function(array $users) {
		 *     foreach ($users as $user) {
		 *         echo "{$user->name}\n";
		 *     }
		 * });
		 * ```
		 */
		public function fetch(): PromiseInterface {

			return $this->sharkord->gateway->sendRpc("query", [
				"path" => "users.getAll",
			])->then(function (array $response) {

				$raw = $response['data']
					?? throw new \RuntimeException("users.getAll response missing 'data'.");

				foreach ($raw as $rawUser) {
					$this->hydrate($rawUser);
				}

				return iterator_to_array($this->cache);

			});

		}

		/**
		 * Returns the underlying Users collection.
		 *
		 * @return UsersCollection
		 */
		public function collection(): UsersCollection {

			return $this->cache;

		}

		/**
		 * Returns the count of cached users.
		 *
		 * @return int
		 */
		public function count(): int {

			return count($this->cache);

		}

	}
	
?>