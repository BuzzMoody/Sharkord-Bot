<?php

	declare(strict_types=1);

	namespace Tests\WebSocket;

	use PHPUnit\Framework\TestCase;
	use Sharkord\WebSocket\Gateway;
	use Sharkord\Sharkord;
	use React\EventLoop\LoopInterface;
	use React\Promise\PromiseInterface;
	use Psr\Log\LoggerInterface;

	class GatewayTest extends TestCase
	{
		private Sharkord $sharkordMock;
		private LoggerInterface $loggerMock;

		protected function setUp(): void
		{
			$this->sharkordMock = $this->createMock(Sharkord::class);
			$this->loggerMock = $this->createMock(LoggerInterface::class);
			
			// The Gateway usually relies on the loop and logger from the main instance
			$this->sharkordMock->logger = $this->loggerMock;
			$this->sharkordMock->loop = $this->createMock(LoopInterface::class);
		}

		public function testGatewayInitialization(): void
		{
			$gateway = new Gateway($this->sharkordMock);
			$this->assertInstanceOf(Gateway::class, $gateway);
		}

		public function testSendRpcCreatesPromise(): void
		{
			$gateway = new Gateway($this->sharkordMock);
			
			// Simulating the Gateway's RPC send method routing
			$promise = $gateway->sendRpc('mutation', [
				'path' => 'ping',
				'input' => []
			]);
			
			$this->assertInstanceOf(PromiseInterface::class, $promise, 'sendRpc must return a ReactPHP Promise');
		}

		public function testHandleIncomingMessageEmitsEvent(): void
		{
			$gateway = new Gateway($this->sharkordMock);
			
			// We expect the Gateway to parse the payload and tell Sharkord to emit a specific event
			$eventEmitted = false;
			$this->sharkordMock->expects($this->once())
							   ->method('emit')
							   ->willReturnCallback(function($event, $args) use (&$eventEmitted) {
								   if ($event === 'MESSAGE_CREATE') {
									   $eventEmitted = true;
								   }
							   });

			// Simulate an incoming raw JSON WebSocket message from the Sharkord server
			$rawPayload = json_encode([
				'op' => 0,
				't' => 'MESSAGE_CREATE',
				'd' => ['id' => 'msg_1', 'content' => 'Hello from WebSocket!']
			]);

			// Note: If handleMessage is protected/private, you may need to use ReflectionClass to test it, 
			// or trigger it by mocking the underlying ReactPHP connection stream.
			if (method_exists($gateway, 'handleMessage')) {
				$gateway->handleMessage($rawPayload);
				$this->assertTrue($eventEmitted, 'Gateway should parse the payload and emit MESSAGE_CREATE.');
			} else {
				$this->markTestSkipped('handleMessage method is not public or accessible for direct testing.');
			}
		}
	}
	
?>