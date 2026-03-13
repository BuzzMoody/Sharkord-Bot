<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Collections\Categories as CategoriesCollection;
	use Sharkord\Models\Category;

	/**
	 * Class CategoryManager
	 *
	 * Manages category lifecycle events, delegating all cache storage to a
	 * Categories collection instance.
	 *
	 * Accessible via `$sharkord->categories`.
	 *
	 * @package Sharkord\Managers
	 *
	 * @example
	 * ```php
	 * $sharkord->on(\Sharkord\Events::CATEGORY_CREATE, function(\Sharkord\Models\Category $category): void {
	 *     echo "New category created: {$category->name}\n";
	 * });
	 *
	 * $sharkord->on(\Sharkord\Events::CATEGORY_UPDATE, function(\Sharkord\Models\Category $category): void {
	 *     echo "Category updated: {$category->name}\n";
	 * });
	 *
	 * $sharkord->on(\Sharkord\Events::CATEGORY_DELETE, function(\Sharkord\Models\Category $category): void {
	 *     echo "Category deleted: {$category->name}\n";
	 * });
	 * ```
	 */
	class CategoryManager {

		private CategoriesCollection $cache;

		/**
		 * CategoryManager constructor.
		 *
		 * @param Sharkord $sharkord The main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {
			$this->cache = new CategoriesCollection($this->sharkord);
		}

		/**
		 * Handles the hydration of a category from the initial join payload.
		 *
		 * @internal
		 * @param array $raw The raw category data.
		 * @return void
		 */
		public function hydrate(array $raw): void {

			$this->cache->add($raw);

		}

		/**
		 * Handles the server-initiated creation of a category.
		 *
		 * Adds the category to the local cache and emits a `categorycreate` event.
		 *
		 * @internal
		 * @param array $raw The raw category data.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::CATEGORY_CREATE, function(\Sharkord\Models\Category $category): void {
		 *     echo "Category '{$category->name}' was created.\n";
		 * });
		 * ```
		 */
		public function onCreate(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot create category: missing 'id' in data.");
				return;
			}

			$this->cache->add($raw);

			$this->sharkord->emit('categorycreate', [$this->cache->get($raw['id'])]);

		}

		/**
		 * Handles a server-pushed update to a category.
		 *
		 * Updates the cached model in place and emits a `categoryupdate` event.
		 *
		 * @internal
		 * @param array $raw The raw category data.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::CATEGORY_UPDATE, function(\Sharkord\Models\Category $category): void {
		 *     echo "Category '{$category->name}' was updated.\n";
		 * });
		 * ```
		 */
		public function onUpdate(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot update category: missing 'id' in data.");
				return;
			}

			$category = $this->cache->update($raw);

			if ($category) {
				$this->sharkord->emit('categoryupdate', [$category]);
			}

		}

		/**
		 * Handles the server-initiated deletion of a category.
		 *
		 * Emits a `categorydelete` event with the cached model before removing it.
		 *
		 * @internal
		 * @param int $id The ID of the deleted category.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::CATEGORY_DELETE, function(\Sharkord\Models\Category $category): void {
		 *     echo "Category '{$category->name}' was deleted.\n";
		 * });
		 * ```
		 */
		public function onDelete(int $id): void {

			$category = $this->cache->get($id);

			if (!$category) {
				$this->sharkord->logger->error("Category ID {$id} doesn't exist, therefore cannot be deleted.");
				return;
			}

			$this->sharkord->emit('categorydelete', [$category]);
			$this->cache->remove($id);

		}

		/**
		 * Retrieves a category by ID.
		 *
		 * @param int $id The category ID.
		 * @return Category|null The cached Category model, or null if not found.
		 *
		 * @example
		 * ```php
		 * $category = $sharkord->categories->get(3);
		 *
		 * if ($category) {
		 *     echo "Found category: {$category->name}\n";
		 * }
		 * ```
		 */
		public function get(int $id): ?Category {

			return $this->cache->get($id);

		}

		/**
		 * Returns the underlying Categories collection.
		 *
		 * @return CategoriesCollection
		 *
		 * @example
		 * ```php
		 * foreach ($sharkord->categories->collection() as $id => $category) {
		 *     echo "{$id}: {$category->name}\n";
		 * }
		 * ```
		 */
		public function collection(): CategoriesCollection {

			return $this->cache;

		}

	}

?>