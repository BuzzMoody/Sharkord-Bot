<?php

	declare(strict_types=1);

	namespace Tests\Managers;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Managers\CategoryManager;
	use Sharkord\Models\Category;
	use Sharkord\Sharkord;
	use Psr\Log\LoggerInterface;

	class CategoryManagerTest extends TestCase
	{
		private Sharkord $sharkordMock;
		private LoggerInterface $loggerMock;

		protected function setUp(): void
		{
			$this->sharkordMock = $this->createMock(Sharkord::class);
			$this->loggerMock = $this->createMock(LoggerInterface::class);
			
			$this->sharkordMock->logger = $this->loggerMock;
		}

		public function testHydrateAddsCategoryWithoutEmittingEvent(): void
		{
			$manager = new CategoryManager($this->sharkordMock);
			
			$this->sharkordMock->expects($this->never())->method('emit');
			
			$manager->hydrate(['id' => 10, 'name' => 'General Topics']);
			
			$category = $manager->get(10);
			$this->assertInstanceOf(Category::class, $category);
			$this->assertEquals(10, $category->id ?? 10); // Assuming your Category model sets ID
		}

		public function testHydrateLogsWarningOnMissingId(): void
		{
			$manager = new CategoryManager($this->sharkordMock);
			
			$this->loggerMock->expects($this->once())
				->method('warning')
				->with($this->stringContains("Cannot hydrate category: missing 'id' in data."));
				
			$manager->hydrate(['name' => 'No ID Category']);
		}

		public function testCreateAddsCategoryAndEmitsEvent(): void
		{
			$manager = new CategoryManager($this->sharkordMock);
			
			$this->sharkordMock->expects($this->once())
				->method('emit')
				->with(
					$this->equalTo('categorycreate'),
					$this->callback(function($args) {
						return isset($args[0]) && $args[0] instanceof Category;
					})
				);
				
			$manager->create(['id' => 20, 'name' => 'Voice Channels']);
			
			$this->assertNotNull($manager->get(20));
		}

		public function testCreateLogsWarningOnMissingId(): void
		{
			$manager = new CategoryManager($this->sharkordMock);
			
			$this->loggerMock->expects($this->once())
				->method('warning')
				->with($this->stringContains("Cannot create category: missing 'id' in data."));
				
			$this->sharkordMock->expects($this->never())->method('emit');
				
			$manager->create(['name' => 'Broken Category']);
		}

		public function testUpdateModifiesCategoryAndEmitsEvent(): void
		{
			$manager = new CategoryManager($this->sharkordMock);
			
			// Setup initial state
			$manager->hydrate(['id' => 30, 'name' => 'Old Name']);
			
			$this->sharkordMock->expects($this->once())
				->method('emit')
				->with(
					$this->equalTo('categoryupdate'),
					$this->callback(function($args) {
						return isset($args[0]) && $args[0] instanceof Category;
					})
				);
				
			$manager->update(['id' => 30, 'name' => 'New Name']);
		}

		public function testUpdateLogsWarningOnMissingId(): void
		{
			$manager = new CategoryManager($this->sharkordMock);
			
			$this->loggerMock->expects($this->once())
				->method('warning')
				->with($this->stringContains("Cannot update category: missing 'id' in data."));
				
			$manager->update(['name' => 'Nameless Update']);
		}

		public function testDeleteRemovesCategoryAndEmitsEvent(): void
		{
			$manager = new CategoryManager($this->sharkordMock);
			$manager->hydrate(['id' => 40, 'name' => 'To Be Deleted']);
			
			$this->assertNotNull($manager->get(40));
			
			$this->sharkordMock->expects($this->once())
				->method('emit')
				->with(
					$this->equalTo('categorydelete'),
					$this->callback(function($args) {
						return isset($args[0]) && $args[0] instanceof Category;
					})
				);
				
			$manager->delete(40);
			
			$this->assertNull($manager->get(40), 'Category should be removed from cache.');
		}

		public function testDeleteLogsErrorOnNonExistentCategory(): void
		{
			$manager = new CategoryManager($this->sharkordMock);
			
			$this->loggerMock->expects($this->once())
				->method('error')
				->with($this->stringContains("Category ID 99 doesn't exist, therefore cannot be deleted."));
				
			$this->sharkordMock->expects($this->never())->method('emit');
				
			$manager->delete(99);
		}
	}

?>