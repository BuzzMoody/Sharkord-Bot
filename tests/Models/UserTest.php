<?php

	declare(strict_types=1);

	namespace Tests\Models;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Models\User;
	use Sharkord\Sharkord;
	use Sharkord\WebSocket\Gateway;
	use Sharkord\Permission;
	use React\Promise\Promise;

	class UserTest extends TestCase
	{
		private $sharkordMock;

		protected function setUp(): void
		{
			$this->sharkordMock = $this->createMock(Sharkord::class);
			
			$botUserMock = $this->createMock(User::class);
			$botUserMock->method('hasPermission')->willReturn(true);
			$this->sharkordMock->bot = $botUserMock;
		}

		public function testUserStatusDefaultToOffline()
		{
			$user = new User($this->sharkordMock, ['id' => 'user_1']);
			$this->assertEquals('offline', $user->status);

			$user->updateStatus('online');
			$this->assertEquals('online', $user->status);
		}

		public function testUserKick()
		{
			$user = new User($this->sharkordMock, ['id' => 'bad_user', 'roleIds' => [2]]);
			
			$gatewayMock = $this->createMock(Gateway::class);
			$gatewayMock->expects($this->once())
				->method('sendRpc')
				->with(
					$this->equalTo('mutation'),
					$this->callback(function($params) {
						return $params['path'] === 'users.kick' 
							&& $params['input']['userId'] === 'bad_user'
							&& $params['input']['reason'] === 'Spamming';
					})
				)
				->willReturn(new Promise(function($resolve) { $resolve(); }));

			$this->sharkordMock->gateway = $gatewayMock;

			$user->kick('Spamming');
		}
	}

?>