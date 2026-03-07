<?php

	declare(strict_types=1);

	namespace Tests\Models;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Models\Category;
	use Sharkord\Sharkord;

	class CategoryTest extends TestCase
	{
		private Sharkord $sharkordMock;

		protected function setUp(): void
		{
			$this->sharkordMock = $this->createMock(Sharkord::class);
		}

		public function testCategoryCreationAndAttributeReading(): void
		{
			$rawData = ['id' => 1, 'name' => 'General Topics', 'position' => 2];
			$category = Category::fromArray($rawData, $this->sharkordMock);

			// Test magic getter
			$this->assertEquals(1, $category->id);
			$this->assertEquals('General Topics', $category->name);
			$this->assertEquals(2, $category->position);
			$this->assertNull($category->nonExistentProperty);
			
			// Test toArray
			$this->assertEquals($rawData, $category->toArray());
		}

		public function testCategoryUpdateFromArray(): void
		{
			$category = new Category($this->sharkordMock, ['id' => 1, 'name' => 'Old']);
			
			$category->updateFromArray(['name' => 'New', 'extra' => 'data']);
			
			$this->assertEquals('New', $category->name);
			$this->assertEquals('data', $category->extra);
		}
	}
	
?>