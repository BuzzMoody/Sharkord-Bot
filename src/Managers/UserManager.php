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
	 * Accessible via `$sharkord->users`.
	 *
	 * @package Sharkord\Managers
	 *
	 * @example
	 * ```php
	 * $user = $sharkord->users->get(42);
	 *
	 * if ($user) {
	 *     echo "{$user->name} is currently {$user->status}.\n";
	 * }
	 *
	 * $sharkord->on(\Sharkord\Events::USER_JOIN, function(\Sharkord\Models\User $user): void {
	 *     echo "{$user->name} just came online.\n";
	 * });
	 * ```
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
		 * Handles the hydration of a user from the initial join payload.
		 *
		 * @internal
		 * @param array $raw The raw user data.
		 * @return void
		 */
		public function hydrate(array $raw): void {

			$this->cache->add($raw);

		}

		/**
		 * Handles the server-initiated creation of a user account.
		 *
		 * Adds the user to the local cache and emits a `usercreate` event.
		 *
		 * @internal
		 * @param array $raw The raw user data.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::USER_CREATE, function(\Sharkord\Models\User $user): void {
		 *     echo "New user account created: {$user->name}\n";
		 * });
		 * ```
		 */
		public function onCreate(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot create user: missing 'id' in data.");
				return;
			}

			$this->cache->add($raw);

			$this->sharkord->emit('usercreate', [$this->cache->get($raw['id'])]);

		}

		/**
		 * Handles a user joining the server (sets their status to online).
		 *
		 * Emits a `userjoin` event with the updated User model.
		 *
		 * @internal
		 * @param array $raw The raw user data.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::USER_JOIN, function(\Sharkord\Models\User $user): void {
		 *     echo "{$user->name} has come online.\n";
		 * });
		 * ```
		 */
		public function onJoin(array $raw): void {

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
		 * Handles a user leaving the server (sets their status to offline).
		 *
		 * Emits a `userleave` event with the updated User model.
		 *
		 * @internal
		 * @param int $id The ID of the user leaving.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::USER_LEAVE, function(\Sharkord\Models\User $user): void {
		 *     echo "{$user->name} has gone offline.\n";
		 * });
		 * ```
		 */
		public function onLeave(int $id): void {

			$user = $this->cache->get($id);

			if ($user) {
				$user->updateStatus('offline');
				$this->sharkord->emit('userleave', [$user]);
			}

		}

		/**
		 * Handles a server-pushed update to user details (e.g. name change, ban, roles).
		 *
		 * Updates the cached model in place. Depending on what changed, one or more
		 * of the `namechange`, `ban`, or `unban` events are emitted.
		 *
		 * @internal
		 * @param array $raw The raw user data.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::USER_BAN, function(\Sharkord\Models\User $user): void {
		 *     echo "{$user->name} was banned.\n";
		 * });
		 *
		 * $sharkord->on(\Sharkord\Events::USER_NAME_CHANGE, function(\Sharkord\Models\User $user): void {
		 *     echo "A user changed their name to: {$user->name}\n";
		 * });
		 * ```
		 */
		public function onUpdate(array $raw): void {

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
		 * Handles the server-initiated deletion of a user account.
		 *
		 * Emits a `userdelete` event with the cached model before removing it.
		 *
		 * @internal
		 * @param int $id The ID of the deleted user.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::USER_DELETE, function(\Sharkord\Models\User $user): void {
		 *     echo "User '{$user->name}' account was deleted.\n";
		 * });
		 * ```
		 */
		public function onDelete(int $id): void {

			$user = $this->cache->get($id);

			if (!$user) {
				$this->sharkord->logger->error("User ID {$id} doesn't exist, therefore cannot be deleted.");
				return;
			}

			$this->sharkord->emit('userdelete', [$user]);
			$this->cache->remove($id);

		}

		/**
		 * Retrieves a cached user by ID.
		 *
		 * @param int|string $id The user ID.
		 * @return User|null The cached User model, or null if not found.
		 *
		 * @example
		 * ```php
		 * $user = $sharkord->users->get(42);
		 *
		 * if ($user) {
		 *     echo "{$user->name} is {$user->status}.\n";
		 * }
		 * ```
		 */
		public function get(int|string $id): ?User {

			return $this->cache->get($id);

		}

		/**
		 * Returns the number of users currently held in the cache.
		 *
		 * @return int
		 *
		 * @example
		 * ```php
		 * echo "Online users cached: " . $sharkord->users->count() . "\n";
		 * ```
		 */
		public function count(): int {

			return count($this->cache);

		}

		/**
		 * Returns the underlying Users collection.
		 *
		 * @return UsersCollection
		 *
		 * @example
		 * ```php
		 * foreach ($sharkord->users->collection() as $id => $user) {
		 *     echo "{$id}: {$user->name}\n";
		 * }
		 * ```
		 */
		public function collection(): UsersCollection {

			return $this->cache;

		}

	}

?>