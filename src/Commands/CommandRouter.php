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
			private Sharkord $sharkord,
			private LoggerInterface $logger
		) {}

		/**
		 * Registers a single command instance to the router.
		 *
		 * @param CommandInterface $command The command object to register.
		 * @return void
		 */
		public function register(CommandInterface $command): void {
			
			$this->commands[$command->getName()] = $command;
			$this->logger->debug("Registered command: " . $command->getName());
			
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
		 * @param Sharkord $sharkord The main framework instance.
		 * @param Message  $message  The received message object.
		 * @return void
		 */
		public function handle(Message $message): void {
			
			$text = $message->content;

			// Quick validation to ensure there is content to parse
			if (empty($text)) {
				return;
			}

			// Extract the command name and arguments using a basic pattern
			// This matches the logic from your original framework
			if (preg_match('/^([a-zA-Z0-9]+)(?:\s+(.*))?$/s', $text, $matches)) {
				
				$commandName = strtolower($matches[1]);
				$args = $matches[2] ?? '';
				
				foreach ($this->commands as $command) {
					if (preg_match($command->getPattern(), $commandName, $cmdMatches)) {
						
						$this->logger->debug("Matched command: $commandName");
						
						try {
							$command->handle($this->sharkord, $message, $args, $cmdMatches);
						} catch (\Exception $e) {
							$this->logger->error("Error executing command '{$commandName}': " . $e->getMessage());
						}
						
						return; // Stop processing once a match is found
					}
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