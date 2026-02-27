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
		 * @param Sharkord            $sharkord        The main bot instance.
		 * @param array<int, Category> $categories Cache of Category models.
		 */
		public function __construct(
			private Sharkord $sharkord,
			private array $categories = []
		) {}

		/**
		 * Handles the hydration of a category.
		 *
		 * @param array $raw The raw category data.
		 * @return void
		 */
		public function hydrate(array $raw): void {
			
			// Instantiating the Category using our new factory method
			$category = Category::fromArray($raw, $this->sharkord);
			$this->categories[$raw['id']] = $category;
			
		}

		/**
		 * Handles the creation of a category.
		 *
		 * @param array $raw The raw category data.
		 * @return void
		 */
		public function create(array $raw): void {
			
			// Instantiating the Category using our new factory method
			$category = Category::fromArray($raw, $this->sharkord);
			$this->categories[$raw['id']] = $category;
			
			$this->sharkord->emit('categorycreate', [$category]);
			
		}

		/**
		 * Handles updates to a category.
		 *
		 * @param array $raw The raw category data.
		 * @return void
		 */		
		public function update(array $raw): void {
			
			if (isset($this->categories[$raw['id']])) {
				
				$this->categories[$raw['id']]->updateFromArray($raw);
				
				$this->sharkord->emit('categoryupdate', [$this->categories[$raw['id']]]);
				
			}
			
		}

		/**
		 * Handles category deletion.
		 *
		 * @param int $id The ID of the deleted category.
		 * @return void
		 */
		 public function delete(int $id): void {
			
			if (!isset($this->categories[$id])) { 
				$this->logger->error("Category ID {$id} doesn't exist, therefore cannot be deleted.");
				return;
			}
			
			$this->sharkord->emit('categorydelete', [$this->categories[$id]]);
			
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