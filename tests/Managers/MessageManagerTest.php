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
			$this->gatewayMock = $this->createMock(Gateway::class);
			
			$this->injectMockProperty($this->sharkordMock, 'gateway', $this->gatewayMock);
		}

		public function testEditMessageResolvesTrueOnSuccess(): void
		{
			$manager = new MessageManager($this->sharkordMock);

			$this->gatewayMock->expects($this->once())
				->method('sendRpc')
				->willReturn(new Promise(function($resolve) {
					$resolve(['type' => 'data']);
				}));

			$promise = $manager->editMessage(123, 'New Text');
			
			$result = null;
			$promise->then(function($val) use (&$result) { $result = $val; });
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
			$promise->then(null, function(\Exception $e) use (&$exception) { $exception = $e; });
			$this->assertInstanceOf(\RuntimeException::class, $exception);
		}

		public function testDeleteMessageResolvesTrueOnSuccess(): void
		{
			$manager = new MessageManager($this->sharkordMock);

			$this->gatewayMock->expects($this->once())
				->method('sendRpc')
				->willReturn(new Promise(function($resolve) {
					$resolve(['type' => 'data']);
				}));

			$promise = $manager->deleteMessage(456);
			
			$result = null;
			$promise->then(function($val) use (&$result) { $result = $val; });
			$this->assertTrue($result);
		}
	}

?>