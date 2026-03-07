<?php

	declare(strict_types=1);

	namespace Tests\Managers;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Managers\MessageManager;
	use Sharkord\Sharkord;
	use Sharkord\WebSocket\Gateway;
	use React\Promise\Promise;

	class MessageManagerTest extends TestCase
	{
		private Sharkord $sharkordMock;
		private $gatewayMock;
		
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
			$this->injectMockProperty($this->sharkordMock, 'gateway', $gatewayMock);
			
			$this->sharkordMock->gateway = $this->gatewayMock;
		}

		public function testEditMessageResolvesTrueOnSuccess(): void
		{
			$manager = new MessageManager($this->sharkordMock);

			$this->gatewayMock->expects($this->once())
				->method('sendRpc')
				->with(
					'mutation',
					$this->callback(function($params) {
						return $params['path'] === 'messages.edit' 
							&& $params['input']['messageId'] === 123
							&& $params['input']['content'] === 'New Text';
					})
				)
				->willReturn(new Promise(function($resolve) {
					$resolve(['type' => 'data']);
				}));

			$promise = $manager->editMessage(123, 'New Text');
			
			$result = null;
			$promise->then(function($val) use (&$result) {
				$result = $val;
			});

			$this->assertTrue($result);
		}

		public function testEditMessageThrowsExceptionOnFailure(): void
		{
			$manager = new MessageManager($this->sharkordMock);

			$this->gatewayMock->method('sendRpc')->willReturn(new Promise(function($resolve) {
				$resolve(['type' => 'error', 'message' => 'Invalid ID']);
			}));

			$promise = $manager->editMessage(999, 'Fail Text');
			
			$exception = null;
			$promise->then(null, function(\Exception $e) use (&$exception) {
				$exception = $e;
			});

			$this->assertInstanceOf(\RuntimeException::class, $exception);
			$this->assertStringContainsString('Failed to edit message', $exception->getMessage());
		}

		public function testDeleteMessageResolvesTrueOnSuccess(): void
		{
			$manager = new MessageManager($this->sharkordMock);

			$this->gatewayMock->expects($this->once())
				->method('sendRpc')
				->with(
					'mutation',
					$this->callback(function($params) {
						return $params['path'] === 'messages.delete' 
							&& $params['input']['messageId'] === 456;
					})
				)
				->willReturn(new Promise(function($resolve) {
					$resolve(['type' => 'data']);
				}));

			$promise = $manager->deleteMessage(456);
			
			$result = null;
			$promise->then(function($val) use (&$result) {
				$result = $val;
			});

			$this->assertTrue($result);
		}
	}
	
?>