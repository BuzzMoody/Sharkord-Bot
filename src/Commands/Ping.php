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
		
		private const RESPONSES = [
			"Pong! Right back at ya.",
			"Ping received. Pong!",
			"Got it!",
			"Ping received, initiating pong sequence... Pong!",
			"Did someone say ping? Pong!",
			"You rang? Pong!",
			"Copy that. Pong!",
			"The answer is always... pong."
		];

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
		public function handle(Message $message, string $args, array $matches): void {
			$message->reply(self::RESPONSES[array_rand(self::RESPONSES)]);
		}

	}

?>