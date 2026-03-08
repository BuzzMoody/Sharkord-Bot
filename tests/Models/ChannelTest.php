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
			$channel = Channel::fromArray(['id' => 'chan_1', 'name' => 'general'], $this->sharkordMock);
			$this->assertEquals('chan_1', $channel->id);
		}

		public function testCategoryRelationshipGetter(): void
		{
			$categoryManagerMock = $this->createMock(CategoryManager::class);
			$categoryMock = $this->createMock(Category::class);
			$categoryMock->method('__get')->with('name')->willReturn('Parent Category');
			
			$categoryManagerMock->method('get')->willReturn($categoryMock);
			$this->sharkordMock->categories = $categoryManagerMock;

			$channel = new Channel($this->sharkordMock, ['id' => 'chan_2', 'categoryId' => 99]);
			$this->assertEquals('Parent Category', $channel->category->name);
		}

		public function testSendMessageResolvesOnSuccess(): void
		{
			$gatewayMock = $this->createMock(Gateway::class);
			$gatewayMock->method('sendRpc')->willReturn(new Promise(function($resolve) {
				$resolve(['type' => 'data']);
			}));
			$this->injectMockProperty($this->sharkordMock, 'gateway', $gatewayMock);

			$channel = new Channel($this->sharkordMock, ['id' => 'chan_3']);
			$promise = $channel->sendMessage('Hello!');
			
			$result = null;
			$promise->then(function($val) use (&$result) { $result = $val; });
			$this->assertTrue($result);
		}

		public function testSendMessageThrowsExceptionOnFailure(): void
		{
			$gatewayMock = $this->createMock(Gateway::class);
			$gatewayMock->method('sendRpc')->willReturn(new Promise(function($resolve) {
				$resolve(['type' => 'error']); 
			}));
			$this->injectMockProperty($this->sharkordMock, 'gateway', $gatewayMock);

			$channel = new Channel($this->sharkordMock, ['id' => 'chan_4']);
			$promise = $channel->sendMessage('Bad message');
			
			$exception = null;
			$promise->then(null, function(\Exception $e) use (&$exception) { $exception = $e; });
			$this->assertInstanceOf(\RuntimeException::class, $exception);
		}
	}

?>