<?php

	declare(strict_types=1);

	namespace Sharkord\Collections;

	use Sharkord\Sharkord;
	use Sharkord\Models\Role;

	/**
	 * Class Roles
	 *
	 * An array-accessible, iterable cache of Role objects keyed by role ID (string).
	 *
	 * Supports lookup by both integer ID and role name via get().
	 *
	 * @implements \ArrayAccess<string, Role>
	 * @implements \IteratorAggregate<string, Role>
	 *
	 * @package Sharkord\Collections
	 *
	 * @example
	 * ```php
	 * // Look up by ID or name
	 * $role = $sharkord->roles->collection()->get(1);
	 * $role = $sharkord->roles->collection()->get('Moderators');
	 *
	 * foreach ($sharkord->roles->collection() as $id => $role) {
	 *     echo "{$role->name}\n";
	 * }
	 *
	 * echo count($sharkord->roles->collection());
	 * ```
	 */
	class Roles implements \ArrayAccess, \Countable, \IteratorAggregate {

		/**
		 * @var array<string, Role> Cached Role models keyed by role ID (string).
		 */
		private array $roles = [];

		/**
		 * Roles constructor.
		 *
		 * @param Sharkord $sharkord Reference to the main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {}

		/**
		 * Adds or merges a role into the collection.
		 *
		 * @internal
		 * @param array $raw The raw role data from the server.
		 * @return void
		 */
		public function add(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot cache role: missing 'id' in data.");
				return;
			}

			$id = (string) $raw['id'];

			if (isset($this->roles[$id])) {
				$this->roles[$id]->updateFromArray($raw);
				return;
			}

			$this->roles[$id] = Role::fromArray($raw, $this->sharkord);

		}

		/**
		 * Merges new data into an already-cached role.
		 *
		 * @internal
		 * @param array $raw The raw role data from the server.
		 * @return Role|null The updated Role model, or null if not cached.
		 */
		public function update(array $raw): ?Role {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot update cached role: missing 'id' in data.");
				return null;
			}

			$id = (string) $raw['id'];

			if (!isset($this->roles[$id])) {
				return null;
			}

			$this->roles[$id]->updateFromArray($raw);

			return $this->roles[$id];

		}

		/**
		 * Removes a role from the collection.
		 *
		 * @internal
		 * @param int|string $id The role ID.
		 * @return void
		 */
		public function remove(int|string $id): void {

			unset($this->roles[(string) $id]);

		}

		/**
		 * Retrieves a role by ID or name.
		 *
		 * @param int|string $identifier The role ID or display name.
		 * @return Role|null
		 */
		public function get(int|string $identifier): ?Role {

			if (is_int($identifier) || ctype_digit((string) $identifier)) {
				return $this->roles[(string)(int) $identifier] ?? null;
			}

			foreach ($this->roles as $role) {
				if ($role->name === $identifier) {
					return $role;
				}
			}

			return null;

		}

		// --- ArrayAccess ---

		public function offsetExists(mixed $offset): bool {

			return isset($this->roles[(string) $offset]);

		}

		public function offsetGet(mixed $offset): ?Role {

			return $this->roles[(string) $offset] ?? null;

		}

		/** @throws \LogicException */
		public function offsetSet(mixed $offset, mixed $value): void {

			throw new \LogicException('Roles is read-only. Use add() to cache a role.');

		}

		/** @throws \LogicException */
		public function offsetUnset(mixed $offset): void {

			throw new \LogicException('Roles is read-only. Use remove() to evict a role.');

		}

		// --- Countable ---

		public function count(): int {

			return count($this->roles);

		}

		// --- IteratorAggregate ---

		/**
		 * @return \ArrayIterator<string, Role>
		 */
		public function getIterator(): \ArrayIterator {

			return new \ArrayIterator($this->roles);

		}

	}

?>