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
	 * @package Sharkord\Managers
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
		 * Handles the hydration of a category.
		 *
		 * @param array $raw The raw category data.
		 * @return void
		 */
		public function hydrate(array $raw): void {

			$this->cache->add($raw);

		}

		/**
		 * Handles the creation of a category.
		 *
		 * @param array $raw The raw category data.
		 * @return void
		 */
		public function create(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot create category: missing 'id' in data.");
				return;
			}

			$this->cache->add($raw);

			$this->sharkord->emit('categorycreate', [$this->cache->get($raw['id'])]);

		}

		/**
		 * Handles updates to a category.
		 *
		 * @param array $raw The raw category data.
		 * @return void
		 */
		public function update(array $raw): void {

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
		 * Handles category deletion.
		 *
		 * @param int $id The ID of the deleted category.
		 * @return void
		 */
		public function delete(int $id): void {

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
		 * @return Category|null
		 */
		public function get(int $id): ?Category {

			return $this->cache->get($id);

		}

		/**
		 * Returns the underlying Categories collection.
		 *
		 * @return CategoriesCollection
		 */
		public function collection(): CategoriesCollection {

			return $this->cache;

		}

	}
	
?>