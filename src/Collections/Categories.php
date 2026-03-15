<?php

	declare(strict_types=1);

	namespace Sharkord\Collections;

	use Sharkord\Sharkord;
	use Sharkord\Models\Category;

	/**
	 * Class Categories
	 *
	 * An array-accessible, iterable cache of Category objects keyed by category ID (string).
	 *
	 * Supports lookup by both integer ID and category name via get().
	 *
	 * @implements \ArrayAccess<string, Category>
	 * @implements \IteratorAggregate<string, Category>
	 *
	 * @package Sharkord\Collections
	 *
	 * @example
	 * ```php
	 * // Look up by ID or name
	 * $category = $sharkord->categories->collection()->get(1);
	 * $category = $sharkord->categories->collection()->get('🎮 Gaming');
	 *
	 * foreach ($sharkord->categories->collection() as $id => $category) {
	 *     echo "{$category->name}\n";
	 * }
	 *
	 * echo count($sharkord->categories->collection());
	 * ```
	 */
	class Categories implements \ArrayAccess, \Countable, \IteratorAggregate {

		/**
		 * @var array<string, Category> Cached Category models keyed by category ID (string).
		 */
		private array $categories = [];

		/**
		 * Categories constructor.
		 *
		 * @param Sharkord $sharkord Reference to the main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {}

		/**
		 * Adds or merges a category into the collection.
		 *
		 * @internal
		 * @param array $raw The raw category data from the server.
		 * @return void
		 */
		public function add(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot cache category: missing 'id' in data.");
				return;
			}

			$id = (string) $raw['id'];

			if (isset($this->categories[$id])) {
				$this->categories[$id]->updateFromArray($raw);
				return;
			}

			$this->categories[$id] = Category::fromArray($raw, $this->sharkord);

		}

		/**
		 * Merges new data into an already-cached category.
		 *
		 * @internal
		 * @param array $raw The raw category data from the server.
		 * @return Category|null The updated Category model, or null if not cached.
		 */
		public function update(array $raw): ?Category {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot update cached category: missing 'id' in data.");
				return null;
			}

			$id = (string) $raw['id'];

			if (!isset($this->categories[$id])) {
				return null;
			}

			$this->categories[$id]->updateFromArray($raw);

			return $this->categories[$id];

		}

		/**
		 * Removes a category from the collection.
		 *
		 * @internal
		 * @param int|string $id The category ID.
		 * @return void
		 */
		public function remove(int|string $id): void {

			unset($this->categories[(string) $id]);

		}

		/**
		 * Retrieves a category by ID or name.
		 *
		 * @param int|string $identifier The category ID or display name.
		 * @return Category|null
		 */
		public function get(int|string $identifier): ?Category {

			if (is_int($identifier) || ctype_digit((string) $identifier)) {
				return $this->categories[(string)(int) $identifier] ?? null;
			}

			foreach ($this->categories as $category) {
				if ($category->name === $identifier) {
					return $category;
				}
			}

			return null;

		}

		// --- ArrayAccess ---

		public function offsetExists(mixed $offset): bool {

			return isset($this->categories[(string) $offset]);

		}

		public function offsetGet(mixed $offset): ?Category {

			return $this->categories[(string) $offset] ?? null;

		}

		/** @throws \LogicException */
		public function offsetSet(mixed $offset, mixed $value): void {

			throw new \LogicException('Categories is read-only. Use add() to cache a category.');

		}

		/** @throws \LogicException */
		public function offsetUnset(mixed $offset): void {

			throw new \LogicException('Categories is read-only. Use remove() to evict a category.');

		}

		// --- Countable ---

		public function count(): int {

			return count($this->categories);

		}

		// --- IteratorAggregate ---

		/**
		 * @return \ArrayIterator<string, Category>
		 */
		public function getIterator(): \ArrayIterator {

			return new \ArrayIterator($this->categories);

		}

	}

?>