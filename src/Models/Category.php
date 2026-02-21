<?php

	declare(strict_types=1);

	namespace Sharkord\Models;
	
	use Sharkord\Sharkord;

	/**
	 * Class Category
	 *
	 * Represents a channel category.
	 *
	 * @package Sharkord\Models
	 */
	class Category {

		/**
		 * @var array Stores all dynamic category data from the API
		 */
		private array $attributes = [];

		/**
		 * Category constructor.
		 *
		 * @param Sharkord $sharkord Reference to the main bot instance.
		 * @param array    $rawData  The raw array of data from the API.
		 */
		public function __construct(
			private Sharkord $sharkord,
			array $rawData
		) {
			$this->updateFromArray($rawData);
		}
		
		/**
		 * Factory method to create a Category from raw API data.
		 */
		public static function fromArray(array $raw, Sharkord $sharkord): self {
			return new self($sharkord, $raw);
		}
		
		/**
		 * Updates the Category's information dynamically.
		 *
		 * @param array $raw The raw Category data from the server.
		 * @return void
		 */
		public function updateFromArray(array $raw): void {
			
			// Merge the new data into our attributes array
			$this->attributes = array_merge($this->attributes, $raw);
			
		}

		/**
		 * Magic getter. This is triggered whenever you try to access a property 
		 * that isn't explicitly defined (e.g., $category->name or $category->position).
		 *
		 * @param string $name Property name.
		 * @return mixed
		 */
		public function __get(string $name): mixed {
			
			return $this->attributes[$name] ?? null;
			
		}

	}
	
?>