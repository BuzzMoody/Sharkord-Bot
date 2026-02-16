<?php

	namespace Sharkord\Commands;

	use Sharkord\Models\Message;

	/**
	 * Class Ping
	 *
	 * A simple command to check if the bot is responsive.
	 * Responds with "Pong!" when invoked.
	 *
	 * @package Sharkord\Commands
	 */
	class Ping implements CommandInterface {

		/**
		 * @inheritDoc
		 */
		public function getName(): string {
			return 'ping';
		}

		/**
		 * @inheritDoc
		 */
		public function getDescription(): string {
			return 'Responds with Pong!';
		}
		
		/**
		 * @inheritDoc
		 */
		public function getPattern(): string {
			return '/^ping$/';
		}

		/**
		 * @inheritDoc
		 */
		public function handle(Message $message, array $args): void {
			$message->reply("Pong!");
		}

	}

?>