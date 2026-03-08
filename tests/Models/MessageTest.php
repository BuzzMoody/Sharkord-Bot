<?php
	
	declare(strict_types=1);

	namespace Tests\Models;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Models\Message;
	use Sharkord\Models\User;
	use Sharkord\Sharkord;
	use Sharkord\Managers\MessageManager;
	use Sharkord\Managers\UserManager;
	use Sharkord\WebSocket\Gateway;
	use React\Promise\Promise;
	use React\Promise\PromiseInterface;

	class MessageTest extends TestCase
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
			$botUserMock->id = 'bot_123';
			$botUserMock->method('hasPermission')->willReturn(true);
			$this->sharkordMock->bot = $botUserMock;
		}

		public function testMessageCreationAndAttributeReading(): void
		{
			$message = Message::fromArray(['id' => 'msg_001', 'content' => '<b>Hello</b>'], $this->sharkordMock);
			$this->assertEquals('Hello', $message->content);
		}

		public function testReactToMessage(): void
		{
			$gatewayMock = $this->createMock(Gateway::class);
			$gatewayMock->method('sendRpc')->willReturn(new Promise(function($resolve) {
				$resolve(['type' => 'data']);
			}));
			$this->injectMockProperty($this->sharkordMock, 'gateway', $gatewayMock);

			$message = Message::fromArray(['id' => 'msg_001'], $this->sharkordMock);
			$promise = $message->react('smile');
			$this->assertInstanceOf(PromiseInterface::class, $promise);
		}

		public function testDeleteMessage(): void
		{
			$messageManagerMock = $this->createMock(MessageManager::class);
			$messageManagerMock->method('deleteMessage')->willReturn(new Promise(function($resolve) { $resolve(); }));
			$this->sharkordMock->messages = $messageManagerMock;
			
			$userManagerMock = $this->createMock(UserManager::class);
			$this->injectMockProperty($this->sharkordMock, 'users', $userManagerMock);

			$message = Message::fromArray(['id' => 'msg_del', 'userId' => 'user_999'], $this->sharkordMock);
			$promise = $message->delete();
			$this->assertInstanceOf(PromiseInterface::class, $promise);
		}
	}
	
?>