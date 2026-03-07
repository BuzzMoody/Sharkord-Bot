<?php

	declare(strict_types=1);

	namespace Tests\Models;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Models\Message;
	use Sharkord\Models\User;
	use Sharkord\Sharkord;
	use Sharkord\Managers\MessageManager;
	use Sharkord\WebSocket\Gateway;
	use React\Promise\Promise;
	use React\Promise\PromiseInterface;

	class MessageTest extends TestCase
	{
		private $sharkordMock;
		
		private function injectMockProperty(object $object, string $property, $value): void
		{
			$reflection = new \ReflectionClass($object);
			$prop = $reflection->getProperty($property);
			$prop->setAccessible(true);
			$prop->setValue($object, $value);
		}

		protected function setUp(): void
		{
		
			// Mock the main Sharkord instance
			$this->sharkordMock = $this->createMock(Sharkord::class);
			
			$this->injectMockProperty($this->sharkordMock, 'users', $this->createMock(\Sharkord\Managers\UserManager::class));
			
			
			// Mock the Bot User for permission checks
			$botUserMock = $this->createMock(User::class);
			$botUserMock->id = 'bot_123';
			$botUserMock->method('hasPermission')->willReturn(true);
			$this->sharkordMock->bot = $botUserMock;
		}

		public function testMessageCreationAndAttributeReading()
		{
			$rawData = [
				'id' => 'msg_001',
				'content' => '<b>Hello World</b>', // Testing strip_tags
				'channelId' => 'chan_123',
				'userId' => 'user_456'
			];

			$message = Message::fromArray($rawData, $this->sharkordMock);

			$this->assertEquals('msg_001', $message->id);
			$this->assertEquals('Hello World', $message->content, 'HTML tags should be stripped from content');
		}

		public function testReactToMessage()
		{
			$rawData = ['id' => 'msg_001'];
			$message = Message::fromArray($rawData, $this->sharkordMock);

			$this->injectMockProperty($this->sharkordMock, 'gateway', $gatewayMock);
			
			// Expect a mutation RPC call to be sent to the Gateway
			$gatewayMock->expects($this->once())
				->method('sendRpc')
				->with(
					$this->equalTo('mutation'),
					$this->callback(function($params) {
						return $params['path'] === 'messages.toggleReaction' 
							&& $params['input']['messageId'] === 'msg_001'
							&& $params['input']['emoji'] === 'smile';
					})
				)
				->willReturn(new Promise(function($resolve) {
					$resolve(['type' => 'data']);
				}));

			$this->sharkordMock->gateway = $gatewayMock;

			// "😄" is passed, LitEmoji should translate it down the line
			$promise = $message->react('😄');
			$this->assertInstanceOf(PromiseInterface::class, $promise);
		}

		public function testDeleteMessage()
		{
			$rawData = [
				'id' => 'msg_delete_1',
				'userId' => 'user_999' // Not the bot's ID
			];
			$message = Message::fromArray($rawData, $this->sharkordMock);

			$messageManagerMock = $this->createMock(MessageManager::class);
			$messageManagerMock->expects($this->once())
				->method('deleteMessage')
				->with($this->equalTo('msg_delete_1'))
				->willReturn(new Promise(function($resolve) { $resolve(); }));

			$this->sharkordMock->messages = $messageManagerMock;

			$promise = $message->delete();
			$this->assertInstanceOf(PromiseInterface::class, $promise);
		}
	}

?>