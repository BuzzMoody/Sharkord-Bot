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
		 * Category constructor.
		 *
		 * @param int    $id       The unique category ID.
		 * @param string $name     The category name.
		 * @param int    $position The sort position of the category.
		 */
		public function __construct(
			public int $id,
			public string $name,
			public int $position
		) {}

	}
	
?>