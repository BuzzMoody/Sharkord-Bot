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
			$this->assertEquals(1, $manager->count());
		}

		public function testCreateEmitsEvent(): void
		{
			$manager = new UserManager($this->sharkordMock);
			
			$this->sharkordMock->expects($this->once())
				->method('emit')
				->with('usercreate', $this->callback(fn($args) => is_array($args)));

			$manager->create(['id' => 3, 'name' => 'Charlie']);
		}

		public function testJoinChangesStatusAndEmitsEvent(): void
		{
			$manager = new UserManager($this->sharkordMock);
			$manager->hydrate(['id' => 4, 'name' => 'Dave']);

			$this->sharkordMock->expects($this->once())->method('emit')->with('userjoin', $this->anything());
			$manager->join(['id' => 4]);
			$this->assertEquals('online', $manager->get(4)->status);
		}

		public function testLeaveChangesStatusAndEmitsEvent(): void
		{
			$manager = new UserManager($this->sharkordMock);
			$manager->hydrate(['id' => 4, 'name' => 'Dave']);
			
			$this->sharkordMock->expects($this->once())->method('emit')->with('userleave', $this->anything());
			$manager->leave(4);
			$this->assertEquals('offline', $manager->get(4)->status);
		}

		public function testUpdateDetectsNameChangeAndBans(): void
		{
			$manager = new UserManager($this->sharkordMock);
			$manager->hydrate(['id' => 5, 'name' => 'Eve', 'banned' => false]);

			$callCount = 0;
			$this->sharkordMock->expects($this->exactly(2))
				->method('emit')
				->willReturnCallback(function($event) use (&$callCount) {
					$callCount++;
					if ($callCount === 1) $this->assertEquals('namechange', $event);
					if ($callCount === 2) $this->assertEquals('ban', $event);
				});

			$manager->update(['id' => 5, 'name' => 'Evil Eve', 'banned' => true]);
		}
	}
	
?>