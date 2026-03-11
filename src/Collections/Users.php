<?php

	declare(strict_types=1);

	namespace Sharkord\Collections;

	use Sharkord\Sharkord;
	use Sharkord\Models\User;

	/**
	 * Class Users
	 *
	 * An array-accessible, iterable cache of User objects keyed by user ID (string).
	 *
	 * Supports lookup by both integer ID and display name via get().
	 *
	 * @implements \ArrayAccess<string, User>
	 * @implements \IteratorAggregate<string, User>
	 *
	 * @package Sharkord\Collections
	 *
	 * @example
	 * ```php
	 * // Look up by ID or name
	 * $user = $sharkord->users->collection()->get(42);
	 * $user = $sharkord->users->collection()->get('Buzz');
	 *
	 * // Iterate all cached users
	 * foreach ($sharkord->users->collection() as $id => $user) {
	 *     echo "{$user->name}\n";
	 * }
	 *
	 * echo count($sharkord->users->collection());
	 * ```
	 */
	class Users implements \ArrayAccess, \Countable, \IteratorAggregate {

		/**
		 * @var array<string, User> Cached User models keyed by user ID (string).
		 */
		private array $users = [];

		/**
		 * Users constructor.
		 *
		 * @param Sharkord $sharkord Reference to the main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {}

		/**
		 * Adds or merges a user into the collection.
		 *
		 * @internal
		 * @param array $raw The raw user data from the server.
		 * @return void
		 */
		public function add(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot cache user: missing 'id' in data.");
				return;
			}

			$id = (string) $raw['id'];

			if (isset($this->users[$id])) {
				$this->users[$id]->updateFromArray($raw);
				return;
			}

			$this->users[$id] = User::fromArray($raw, $this->sharkord);

		}

		/**
		 * Merges new data into an already-cached user.
		 *
		 * @internal
		 * @param array $raw The raw user data from the server.
		 * @return User|null The updated User model, or null if not cached.
		 */
		public function update(array $raw): ?User {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot update cached user: missing 'id' in data.");
				return null;
			}

			$id = (string) $raw['id'];

			if (!isset($this->users[$id])) {
				return null;
			}

			$this->users[$id]->updateFromArray($raw);

			return $this->users[$id];

		}

		/**
		 * Removes a user from the collection.
		 *
		 * @internal
		 * @param int|string $id The user ID.
		 * @return void
		 */
		public function remove(int|string $id): void {

			unset($this->users[(string) $id]);

		}

		/**
		 * Retrieves a user by ID or display name.
		 *
		 * @param int|string $identifier The user ID or display name.
		 * @return User|null
		 */
		public function get(int|string $identifier): ?User {

			if (is_int($identifier) || ctype_digit((string) $identifier)) {
				return $this->users[(string)(int) $identifier] ?? null;
			}

			foreach ($this->users as $user) {
				if ($user->name === $identifier) {
					return $user;
				}
			}

			return null;

		}

		// --- ArrayAccess ---

		public function offsetExists(mixed $offset): bool {

			return isset($this->users[(string) $offset]);

		}

		public function offsetGet(mixed $offset): ?User {

			return $this->users[(string) $offset] ?? null;

		}

		/** @throws \LogicException */
		public function offsetSet(mixed $offset, mixed $value): void {

			throw new \LogicException('Users is read-only. Use add() to cache a user.');

		}

		/** @throws \LogicException */
		public function offsetUnset(mixed $offset): void {

			throw new \LogicException('Users is read-only. Use remove() to evict a user.');

		}

		// --- Countable ---

		public function count(): int {

			return count($this->users);

		}

		// --- IteratorAggregate ---

		/**
		 * @return \ArrayIterator<string, User>
		 */
		public function getIterator(): \ArrayIterator {

			return new \ArrayIterator($this->users);

		}

	}
	
?>