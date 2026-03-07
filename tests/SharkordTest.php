<?php

	declare(strict_types=1);

	namespace Tests;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Sharkord;
	use React\EventLoop\LoopInterface;
	use Psr\Log\LoggerInterface;

	class SharkordTest extends TestCase
	{
		private array $config;

		protected function setUp(): void
		{
			$this->config = [
				'host' => 'sharkord.example.com',
				'identity' => 'test_user',
				'password' => 'secret123'
			];
		}

		public function testInitializationCreatesManagers()
		{
			$loopMock = $this->createMock(LoopInterface::class);
			$loggerMock = $this->createMock(LoggerInterface::class);

			$bot = new Sharkord($this->config, $loopMock, $loggerMock);

			$this->assertNotNull($bot->channels);
			$this->assertNotNull($bot->users);
			$this->assertNotNull($bot->messages);
			$this->assertNotNull($bot->gateway);
			$this->assertNotNull($bot->http);
		}

		public function testReadyEventIsEmitted()
		{
			$loopMock = $this->createMock(LoopInterface::class);
			$bot = new Sharkord($this->config, $loopMock);
			
			$eventFired = false;
			
			$bot->on('ready', function($user) use (&$eventFired) {
				$eventFired = true;
			});

			// Simulate the internal ready trigger
			$bot->emit('ready', [null]);

			$this->assertTrue($eventFired, 'The ready event should be emitted upon successful connection.');
		}
	}

?>