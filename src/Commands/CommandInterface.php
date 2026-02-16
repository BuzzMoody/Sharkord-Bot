<?php

	namespace Sharkord\Commands;

	use Sharkord\Models\Message;

	/**
	 * Interface CommandInterface
	 *
	 * Defines the contract that all bot commands must follow.
	 *
	 * @package Sharkord\Commands
	 */
	interface CommandInterface {

		/**
		 * Retrieves the unique name of the command.
		 *
		 * This name is used to invoke the command (e.g., "ping" for "!ping").
		 *
		 * @return string The command name.
		 */
		public function getName(): string;

		/**
		 * Retrieves a brief description of what the command does.
		 *
		 * @return string The command description.
		 */
		public function getDescription(): string;
		
		/**
		 * A regex pattern match that will trigger the command
		 *
		 * @return string The command patttern.
		 */
		public function getPattern(): string;

		/**
		 * Handles the execution of the command.
		 *
		 * @param Message $message The message object that triggered the command.
		 * @param array   $args    Array of arguments passed with the command.
		 * @return void
		 */
		public function handle(Message $message, string $args, array $matches): void;

	}
	
?>