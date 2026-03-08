<?php
	
	declare(strict_types=1);

	namespace Tests\Models;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Models\User;
	use Sharkord\Sharkord;
	use Sharkord\WebSocket\Gateway;
	use React\Promise\Promise;

	class UserTest extends TestCase
	{
		private Sharkord $sharkordMock;

		private function injectMockProperty(object $object, string $property, $value): void
		{
			$reflection = new \ReflectionClass($object);
			$prop = $reflection->getProperty($property);
			$prop->setAccessible(true);
			$prop->setValue($object, $value);
		}

		protected function setUp(): void
		{
			$this->sharkordMock = $this->createMock(Sharkord::class);
			
			$botUserMock = $this->createMock(User::class);
			$botUserMock->method('hasPermission')->willReturn(true);
			$this->sharkordMock->bot = $botUserMock;
		}

		public function testUserStatusDefaultToOffline(): void
		{
			$user = new User($this->sharkordMock, ['id' => 'user_1']);
			$this->assertEquals('offline', $user->status);

			$user->updateStatus('online');
			$this->assertEquals('online', $user->status);
		}

		public function testUserKick(): void
		{
			$gatewayMock = $this->createMock(Gateway::class);
			$gatewayMock->expects($this->once())->method('sendRpc')
				->willReturn(new Promise(function($resolve) { $resolve(); }));
				
			$this->injectMockProperty($this->sharkordMock, 'gateway', $gatewayMock);

			$user = new User($this->sharkordMock, ['id' => 'bad_user', 'roleIds' => [2]]);
			$user->kick('Spamming');
		}
	}
	
?>