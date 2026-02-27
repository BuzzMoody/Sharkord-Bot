<?php

	declare(strict_types=1);

	namespace Sharkord\Commands;

	use Psr\Log\LoggerInterface;
	use Sharkord\Sharkord;
	use Sharkord\Models\Message;

	/**
	 * Class CommandRouter
	 *
	 * Responsible for loading, registering, and executing chat commands.
	 *
	 * @package Sharkord\Commands
	 */
	class CommandRouter {

		/**
		 * @var array<string, CommandInterface> Registry of available commands.
		 */
		private array $commands = [];

		/**
		 * CommandRouter constructor.
		 *
		 * @param LoggerInterface $logger The PSR-3 logger instance.
		 */
		public function __construct(
			private Sharkord $sharkord
		) {}

		/**
		 * Registers a single command instance to the router.
		 *
		 * @param CommandInterface $command The command object to register.
		 * @return void
		 */
		public function register(CommandInterface $command): void {
			
			$this->commands[$command->getName()] = $command;
			$this->sharkord->logger->debug("Registered command: " . $command->getName());
			
		}

		/**
		 * Automatically loads and registers all command classes from a specific directory.
		 *
		 * @param string $directory The absolute path to the directory containing command classes.
		 * @param string $namespace (Optional) The namespace used in the command files. Default is empty (global).
		 * @return void
		 */
		public function loadFromDirectory(string $directory, string $namespace = ''): void {
			
			$namespace = rtrim($namespace, '\\');

			foreach (glob($directory . '/*.php') as $file) {
				
				require_once $file;
				$className = basename($file, '.php');
				$fullClassName = $namespace ? $namespace . '\\' . $className : $className;

				if (class_exists($fullClassName)) {
					$reflection = new \ReflectionClass($fullClassName);
					
					if ($reflection->implementsInterface(CommandInterface::class) && !$reflection->isAbstract()) {
						$this->register(new $fullClassName());
					}
				}
				
			}
			
		}

		/**
		 * Checks if a received message matches a command pattern and executes it.
		 *
		 * @param Message  $message  The received message object.
		 * @param Arrat    $matches  The original regex matches
		 * @return void
		 */
		public function handle(Message $message, array $matches): void {

			$commandName = strtolower($matches[1]);
			$args = $matches[2] ?? '';
			
			foreach ($this->commands as $command) {
				if (preg_match($command->getPattern(), $commandName, $cmdMatches)) {
					
					$this->sharkord->logger->debug("Matched command: $commandName");
					
					try {
						$command->handle($this->sharkord, $message, $args, $cmdMatches);
					} catch (\Exception $e) {
						$this->sharkord->logger->error("Error executing command '{$commandName}': " . $e->getMessage());
					}
					
					return; // Stop processing once a match is found
				}
			}
			
		}

		/**
		 * Retrieves all registered commands. Useful for generating "Help" menus.
		 *
		 * @return array<string, CommandInterface>
		 */
		public function getCommands(): array {
			return $this->commands;
		}

	}

?>