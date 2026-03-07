<?php

	declare(strict_types=1);

	namespace Tests\Models;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Models\Channel;
	use Sharkord\Models\Category;
	use Sharkord\Managers\CategoryManager;
	use Sharkord\WebSocket\Gateway;
	use Sharkord\Sharkord;
	use React\Promise\Promise;

	class ChannelTest extends TestCase
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
		}

		public function testChannelCreationAndAttributeReading(): void
		{
			$rawData = ['id' => 'chan_1', 'name' => 'general', 'type' => 'text'];
			$channel = Channel::fromArray($rawData, $this->sharkordMock);

			$this->assertEquals('chan_1', $channel->id);
			$this->assertEquals('general', $channel->name);
		}

		public function testCategoryRelationshipGetter(): void
		{
			// 1. Setup a fake category manager that returns a mock category
			$categoryManagerMock = $this->createMock(CategoryManager::class);
			$categoryMock = $this->createMock(Category::class);
			$categoryMock->method('__get')->with('name')->willReturn('Parent Category');
			
			$categoryManagerMock->expects($this->once())
				->method('get')
				->with(99)
				->willReturn($categoryMock);
				
			$this->sharkordMock->categories = $categoryManagerMock;

			// 2. Create the channel with a categoryId
			$channel = new Channel($this->sharkordMock, ['id' => 'chan_2', 'categoryId' => 99]);

			// 3. Test that accessing $channel->category routes to the manager
			$retrievedCategory = $channel->category;
			
			$this->assertNotNull($retrievedCategory);
			$this->assertEquals('Parent Category', $retrievedCategory->name);
		}

		public function testSendMessageResolvesOnSuccess(): void
		{
			$channel = new Channel($this->sharkordMock, ['id' => 'chan_3']);
			
			$this->injectMockProperty($this->sharkordMock, 'gateway', $gatewayMock);
			$gatewayMock->expects($this->once())
				->method('sendRpc')
				->with(
					'mutation',
					$this->callback(function($params) {
						return $params['path'] === 'messages.send' 
							&& $params['input']['channelId'] === 'chan_3'
							&& $params['input']['content'] === '<p>Hello &amp; Welcome!</p>'; // Tests htmlspecialchars
					})
				)
				->willReturn(new Promise(function($resolve) {
					$resolve(['type' => 'data']); // Success payload
				}));
				
			$this->sharkordMock->gateway = $gatewayMock;

			$promise = $channel->sendMessage('Hello & Welcome!');
			
			$result = null;
			$promise->then(function($val) use (&$result) {
				$result = $val;
			});

			$this->assertTrue($result);
		}

		public function testSendMessageThrowsExceptionOnFailure(): void
		{
			$channel = new Channel($this->sharkordMock, ['id' => 'chan_4']);
			
			$gatewayMock = $this->createMock(Gateway::class);
			$gatewayMock->method('sendRpc')->willReturn(new Promise(function($resolve) {
				$resolve(['type' => 'error', 'message' => 'Missing permissions']); 
			}));
				
			$this->sharkordMock->gateway = $gatewayMock;

			$promise = $channel->sendMessage('Bad message');
			
			$exception = null;
			$promise->then(null, function(\Exception $e) use (&$exception) {
				$exception = $e;
			});

			$this->assertInstanceOf(\RuntimeException::class, $exception);
			$this->assertStringContainsString('Failed to send message', $exception->getMessage());
		}
	}
	
?>