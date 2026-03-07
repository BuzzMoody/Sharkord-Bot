<?php
	
	declare(strict_types=1);

	namespace Tests\Managers;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Managers\RoleManager;
	use Sharkord\Models\Role;
	use Sharkord\Sharkord;
	use Psr\Log\LoggerInterface;

	class RoleManagerTest extends TestCase
	{
		private $sharkordMock;
		private $loggerMock;

		protected function setUp(): void
		{
			// Mock the main Sharkord instance and the Logger
			$this->sharkordMock = $this->createMock(Sharkord::class);
			$this->loggerMock = $this->createMock(LoggerInterface::class);
			
			$this->sharkordMock->logger = $this->loggerMock;
		}

		public function testHydrateAddsRoleWithoutEmittingEvent(): void
		{
			$manager = new RoleManager($this->sharkordMock);
			
			// Hydrate should NOT emit an event
			$this->sharkordMock->expects($this->never())->method('emit');
			
			$manager->hydrate(['id' => 1, 'name' => 'Admin']);
			
			$role = $manager->get(1);
			$this->assertInstanceOf(Role::class, $role);
			$this->assertNotNull($role);
		}

		public function testCreateAddsRoleAndEmitsEvent(): void
		{
			$manager = new RoleManager($this->sharkordMock);
			
			// Create SHOULD emit the 'rolecreate' event with the new Role object
			$this->sharkordMock->expects($this->once())
				->method('emit')
				->with(
					$this->equalTo('rolecreate'),
					$this->callback(function($args) {
						return isset($args[0]) && $args[0] instanceof Role;
					})
				);
				
			$manager->create(['id' => 2, 'name' => 'Moderator']);
			
			$role = $manager->get(2);
			$this->assertInstanceOf(Role::class, $role);
		}

		public function testUpdateModifiesRoleAndEmitsEvent(): void
		{
			$manager = new RoleManager($this->sharkordMock);
			
			// First hydrate a role so it exists in the manager's cache
			$manager->hydrate(['id' => 3, 'name' => 'Member']);
			
			// Now test the update
			$this->sharkordMock->expects($this->once())
				->method('emit')
				->with(
					$this->equalTo('roleupdate'),
					$this->callback(function($args) {
						return isset($args[0]) && $args[0] instanceof Role;
					})
				);
				
			$manager->update(['id' => 3, 'name' => 'Verified Member']);
			
			// We can't easily assert the internal properties of the Role model without knowing its structure,
			// but we can ensure the event fired correctly and the object is still in cache.
			$this->assertNotNull($manager->get(3));
		}

		public function testUpdateIgnoresNonExistentRole(): void
		{
			$manager = new RoleManager($this->sharkordMock);
			
			// Should not emit anything because the role ID 99 doesn't exist
			$this->sharkordMock->expects($this->never())->method('emit');
			
			$manager->update(['id' => 99, 'name' => 'Ghost Role']);
		}

		public function testDeleteRemovesRoleAndEmitsEvent(): void
		{
			$manager = new RoleManager($this->sharkordMock);
			$manager->hydrate(['id' => 4, 'name' => 'Temporary']);
			
			$this->assertNotNull($manager->get(4));
			
			$this->sharkordMock->expects($this->once())
				->method('emit')
				->with(
					$this->equalTo('roledelete'),
					$this->callback(function($args) {
						return isset($args[0]) && $args[0] instanceof Role;
					})
				);
				
			$manager->delete(4);
			
			$this->assertNull($manager->get(4), 'Role should be removed from the manager after deletion');
		}

		public function testDeleteLogsErrorWhenRoleDoesNotExist(): void
		{
			$manager = new RoleManager($this->sharkordMock);
			
			$this->sharkordMock->expects($this->never())->method('emit');
			
			$this->loggerMock->expects($this->once())
				->method('error')
				->with($this->stringContains("Role ID 5 doesn't exist, therefore cannot be deleted."));
				
			$manager->delete(5);
		}
	}
	
?>