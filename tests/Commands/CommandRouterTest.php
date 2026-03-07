<?php
	
	declare(strict_types=1);

	namespace Tests\Commands;

	use PHPUnit\Framework\TestCase;
	use Sharkord\Commands\CommandRouter;
	use Sharkord\Commands\CommandInterface;
	use Sharkord\Models\Message;
	use Sharkord\Sharkord;
	use Psr\Log\LoggerInterface;

	class CommandRouterTest extends TestCase
	{
		private Sharkord $sharkordMock;
		private LoggerInterface $loggerMock;

		protected function setUp(): void
		{
			$this->sharkordMock = $this->createMock(Sharkord::class);
			$this->loggerMock = $this->createMock(LoggerInterface::class);
			
			// CommandRouter heavily uses the logger, so we map it to the Sharkord mock
			$this->sharkordMock->logger = $this->loggerMock;
		}

		public function testRegisterAndGetCommands(): void
		{
			$router = new CommandRouter($this->sharkordMock);
			
			// We expect a debug log when registering
			$this->loggerMock->expects($this->once())
				->method('debug')
				->with($this->stringContains('Registered command:'));

			$commandMock = $this->createMock(CommandInterface::class);
			$commandMock->method('getName')->willReturn('ping');

			$router->register($commandMock);
			$commands = $router->getCommands();

			$this->assertCount(1, $commands);
			$this->assertArrayHasKey('ping', $commands);
			$this->assertSame($commandMock, $commands['ping']);
		}

		public function testHandleMatchesAndExecutesCommand(): void
		{
			$router = new CommandRouter($this->sharkordMock);
			$messageMock = $this->createMock(Message::class);

			// Create a command that matches the pattern '/^ping$/'
			$commandMock = $this->createMock(CommandInterface::class);
			$commandMock->method('getName')->willReturn('ping');
			$commandMock->method('getPattern')->willReturn('/^ping$/');
			
			// Ensure the command's handle method gets called with the right arguments
			$commandMock->expects($this->once())
				->method('handle')
				->with(
					$this->equalTo($this->sharkordMock),
					$this->equalTo($messageMock),
					$this->equalTo('some args'),
					$this->anything() // $cmdMatches from preg_match
				);

			$router->register($commandMock);

			// Simulate regex matches parsed from a chat message (e.g. "!ping some args")
			$matches = [
				0 => '!ping some args',
				1 => 'Ping', // The router runs strtolower() on this
				2 => 'some args'
			];

			$router->handle($messageMock, $matches);
		}

		public function testLoadFromDirectory(): void
		{
			// To test file loading safely, we can create a temporary PHP file
			$tempDir = sys_get_temp_dir() . '/sharkord_test_cmds_' . uniqid();
			mkdir($tempDir);
			
			$phpCode = <<<PHP
	<?php
	namespace DummyNamespace;
	use Sharkord\Commands\CommandInterface;
	use Sharkord\Sharkord;
	use Sharkord\Models\Message;

	class DummyCommand implements CommandInterface {
		public function getName(): string { return 'dummy'; }
		public function getDescription(): string { return 'test'; }
		public function getPattern(): string { return '/^dummy$/'; }
		public function handle(Sharkord \$s, Message \$m, string \$a, array \$mat): void {}
	}
	PHP;
			file_put_contents($tempDir . '/DummyCommand.php', $phpCode);

			$router = new CommandRouter($this->sharkordMock);
			$router->loadFromDirectory($tempDir, 'DummyNamespace');

			$commands = $router->getCommands();
			$this->assertArrayHasKey('dummy', $commands, 'The dummy command should be loaded from the directory');

			// Cleanup
			unlink($tempDir . '/DummyCommand.php');
			rmdir($tempDir);
		}
	}

?>