<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Models\Category;

	/**
	 * Class CategoryManager
	 *
	 * Manages the state, creation, updating, and deletion of categories.
	 *
	 * @package Sharkord\Managers
	 */
	class CategoryManager {

		/**
		 * CategoryManager constructor.
		 *
		 * @param Sharkord            $bot        The main bot instance.
		 * @param array<int, Category> $categories Cache of Category models.
		 */
		public function __construct(
			private Sharkord $bot,
			private array $categories = []
		) {}

		/**
		 * Handles creating or updating a category in the cache.
		 *
		 * @param array $raw The raw category data.
		 * @return void
		 */
		public function handleCreate(array $raw): void {
			
			$category = new Category($raw['id'], $raw['name'], $raw['position']);
			$this->categories[$raw['id']] = $category;
			
		}

		/**
		 * Handles updates to a category.
		 *
		 * @param array $raw The raw category data.
		 * @return void
		 */
		public function handleUpdate(array $raw): void {
			
			if (isset($this->categories[$raw['id']])) {
				$cat = $this->categories[$raw['id']];
				$cat->name = $raw['name'];
				$cat->position = $raw['position'];
			}
			
		}

		/**
		 * Handles category deletion.
		 *
		 * @param int $id The ID of the deleted category.
		 * @return void
		 */
		public function handleDelete(int $id): void {
			
			unset($this->categories[$id]);
			
		}

		/**
		 * Retrieves a category by ID.
		 *
		 * @param int $id The category ID.
		 * @return Category|null
		 */
		public function get(int $id): ?Category {
			
			return $this->categories[$id] ?? null;
			
		}

	}
?>