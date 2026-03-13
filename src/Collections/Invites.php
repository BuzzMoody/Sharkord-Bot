<?php

	declare(strict_types=1);

	namespace Sharkord\Collections;

	use Sharkord\Sharkord;
	use Sharkord\Models\Invite;

	/**
	 * Class Invites
	 *
	 * An array-accessible, iterable cache of Invite objects keyed by invite ID.
	 *
	 * @implements \ArrayAccess<int, Invite>
	 * @implements \IteratorAggregate<int, Invite>
	 *
	 * @package Sharkord\Collections
	 *
	 * @example
	 * ```php
	 * $invites = $sharkord->invites->collection();
	 *
	 * foreach ($invites as $id => $invite) {
	 *     echo "{$invite->code} — {$invite->uses} uses\n";
	 * }
	 *
	 * $invite = $invites->get(5);
	 *
	 * echo count($invites) . " active invites\n";
	 * ```
	 */
	class Invites implements \ArrayAccess, \Countable, \IteratorAggregate {

		/**
		 * @var array<int, Invite> Cached Invite models keyed by invite ID.
		 */
		private array $invites = [];

		/**
		 * Invites constructor.
		 *
		 * @param Sharkord $sharkord Reference to the main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {}

		/**
		 * Adds or merges an invite into the collection.
		 *
		 * @internal
		 * @param array<string, mixed> $raw The raw invite data from the API.
		 * @return void
		 */
		public function add(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot cache invite: missing 'id' in data.");
				return;
			}

			$id = (int) $raw['id'];

			if (isset($this->invites[$id])) {
				$this->invites[$id]->updateFromArray($raw);
				return;
			}

			$this->invites[$id] = Invite::fromArray($raw, $this->sharkord);

		}

		/**
		 * Merges new data into an already-cached invite.
		 *
		 * @internal
		 * @param array<string, mixed> $raw The raw invite data from the API.
		 * @return Invite|null The updated Invite model, or null if not cached.
		 */
		public function update(array $raw): ?Invite {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot update cached invite: missing 'id' in data.");
				return null;
			}

			$id = (int) $raw['id'];

			if (!isset($this->invites[$id])) {
				return null;
			}

			$this->invites[$id]->updateFromArray($raw);

			return $this->invites[$id];

		}

		/**
		 * Removes an invite from the collection.
		 *
		 * @internal
		 * @param int $id The invite ID.
		 * @return void
		 */
		public function remove(int $id): void {

			unset($this->invites[$id]);

		}

		/**
		 * Retrieves an invite by ID.
		 *
		 * @param int $id The invite ID.
		 * @return Invite|null The cached Invite model, or null if not found.
		 */
		public function get(int $id): ?Invite {

			return $this->invites[$id] ?? null;

		}

		/**
		 * Returns all cached invites as an array.
		 *
		 * @return array<int, Invite>
		 */
		public function all(): array {

			return $this->invites;

		}

		/**
		 * Clears all cached invites.
		 *
		 * @internal
		 * @return void
		 */
		public function clear(): void {

			$this->invites = [];

		}

		// --- ArrayAccess ---

		public function offsetExists(mixed $offset): bool {

			return isset($this->invites[(int) $offset]);

		}

		public function offsetGet(mixed $offset): ?Invite {

			return $this->invites[(int) $offset] ?? null;

		}

		/** @throws \LogicException */
		public function offsetSet(mixed $offset, mixed $value): void {

			throw new \LogicException('Invites is read-only. Use add() to cache an invite.');

		}

		/** @throws \LogicException */
		public function offsetUnset(mixed $offset): void {

			throw new \LogicException('Invites is read-only. Use remove() to evict an invite.');

		}

		// --- Countable ---

		public function count(): int {

			return count($this->invites);

		}

		// --- IteratorAggregate ---

		/**
		 * @return \ArrayIterator<int, Invite>
		 */
		public function getIterator(): \ArrayIterator {

			return new \ArrayIterator($this->invites);

		}

	}

?>