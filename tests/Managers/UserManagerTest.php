<?php

	declare(strict_types=1);

	namespace Tests\Managers;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Managers\UserManager;
	use Sharkord\Models\User;
	use Sharkord\Sharkord;
	use Psr\Log\LoggerInterface;

	class UserManagerTest extends TestCase
	{
		private Sharkord $sharkordMock;
		private LoggerInterface $loggerMock;

		protected function setUp(): void
		{
			$this->sharkordMock = $this->createMock(Sharkord::class);
			$this->loggerMock = $this->createMock(LoggerInterface::class);
			$this->sharkordMock->logger = $this->loggerMock;
		}

		public function testHydrateAndGetCount(): void
		{
			$manager = new UserManager($this->sharkordMock);
			$manager->hydrate(['id' => 1, 'name' => 'Alice']);
			$manager->hydrate(['id' => 2, 'name' => 'Bob']);

			$this->assertEquals(2, $manager->count());
			$this->assertInstanceOf(User::class, $manager->get(1));
		}

		public function testCreateEmitsEvent(): void
		{
			$manager = new UserManager($this->sharkordMock);
			
			$this->sharkordMock->expects($this->once())
				->method('emit')
				->with('usercreate', $this->isType('array'));

			$manager->create(['id' => 3, 'name' => 'Charlie']);
			$this->assertEquals(1, $manager->count());
		}

		public function testJoinAndLeaveChangeStatusAndEmitEvents(): void
		{
			$manager = new UserManager($this->sharkordMock);
			$manager->hydrate(['id' => 4, 'name' => 'Dave']);

			// Test Join
			$this->sharkordMock->expects($this->exactly(2))
				->method('emit')
				->withConsecutive(
					['userjoin', $this->anything()],
					['userleave', $this->anything()]
				);

			$manager->join(['id' => 4]);
			$this->assertEquals('online', $manager->get(4)->status);

			// Test Leave
			$manager->leave(4);
			$this->assertEquals('offline', $manager->get(4)->status);
		}

		public function testUpdateDetectsNameChangeAndBans(): void
		{
			$manager = new UserManager($this->sharkordMock);
			$manager->hydrate(['id' => 5, 'name' => 'Eve', 'banned' => false]);

			// Expect namechange and ban events
			$this->sharkordMock->expects($this->exactly(2))
				->method('emit')
				->withConsecutive(
					['namechange', $this->anything()],
					['ban', $this->anything()]
				);

			// Change name and set banned to true
			$manager->update(['id' => 5, 'name' => 'Evil Eve', 'banned' => true]);

			$user = $manager->get(5);
			$this->assertEquals('Evil Eve', $user->name);
			
			// Now test unban
			$this->sharkordMock->expects($this->once())
				->method('emit')
				->with('unban', $this->anything());
				
			$manager->update(['id' => 5, 'name' => 'Evil Eve', 'banned' => false]);
		}

		public function testDeleteRemovesUserAndEmitsEvent(): void
		{
			$manager = new UserManager($this->sharkordMock);
			$manager->hydrate(['id' => 6, 'name' => 'Frank']);

			$this->sharkordMock->expects($this->once())
				->method('emit')
				->with('userdelete', $this->anything());

			$manager->delete(6);
			$this->assertNull($manager->get(6));
			$this->assertEquals(0, $manager->count());
		}

		public function testDeleteLogsErrorOnNonExistentUser(): void
		{
			$manager = new UserManager($this->sharkordMock);

			$this->loggerMock->expects($this->once())
				->method('error')
				->with($this->stringContains("User ID 99 doesn't exist"));

			$manager->delete(99);
		}

		public function testGetFindsByIdentifier(): void
		{
			$manager = new UserManager($this->sharkordMock);
			$manager->hydrate(['id' => 10, 'name' => 'Grace']);

			// Find by INT ID
			$this->assertEquals('Grace', $manager->get(10)->name);
			// Find by String ID
			$this->assertEquals('Grace', $manager->get('10')->name);
			// Find by Name
			$this->assertEquals(10, $manager->get('Grace')->id);
			// Not found
			$this->assertNull($manager->get('Unknown'));
		}
	}
	
?>