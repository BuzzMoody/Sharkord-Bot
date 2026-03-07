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
		private array $config = ['host' => 'example.com'];
		private $loopMock;
		private $loggerMock;

		protected function setUp(): void
		{
			$this->loopMock = $this->createMock(LoopInterface::class);
			$this->loggerMock = $this->createMock(LoggerInterface::class);
		}

		public function testGatewayInitialization(): void
		{
			// Gateway constructor signature fix
			$gateway = new Gateway($this->config, $this->loopMock, $this->loggerMock);
			$this->assertInstanceOf(Gateway::class, $gateway);
		}

		public function testSendRpcCreatesPromise(): void
		{
			$gateway = new Gateway($this->config, $this->loopMock, $this->loggerMock);
			
			$promise = $gateway->sendRpc('mutation', [
				'path' => 'ping',
				'input' => []
			]);
			
			$this->assertInstanceOf(PromiseInterface::class, $promise);
		}

		public function testHandleIncomingMessageEmitsEvent(): void
		{
			$gateway = new Gateway($this->config, $this->loopMock, $this->loggerMock);
			
			if (method_exists($gateway, 'handleMessage')) {
				// Emitting to the gateway trait
				$eventEmitted = false;
				$gateway->on('MESSAGE_CREATE', function() use (&$eventEmitted) {
					$eventEmitted = true;
				});

				$rawPayload = json_encode([
					'op' => 0,
					't' => 'MESSAGE_CREATE',
					'd' => ['id' => 'msg_1', 'content' => 'Hello from WebSocket!']
				]);

				$gateway->handleMessage($rawPayload);
				$this->assertTrue($eventEmitted);
			} else {
				$this->markTestSkipped('handleMessage is not public.');
			}
		}
	}
	
?>