<?php

	namespace Sharkord\Models;

	use Sharkord\Sharkord;

	/**
	 * Class Channel
	 *
	 * Represents a chat channel on the server.
	 *
	 * @package Sharkord\Models
	 */
	class Channel {

		/**
		 * Channel constructor.
		 *
		 * @param int      $id   The unique channel ID.
		 * @param string   $name The channel name.
		 * @param string   $type The channel type (e.g., 'TEXT').
		 * @param Sharkord $bot  Reference to the main bot instance.
		 */
		public function __construct(
			public int $id,
			public string $name,
			public string $type,
			private Sharkord $bot // We store the bot instance here
		) {}

		/**
		 * Sends a message to this channel.
		 *
		 * @param string $text The message content.
		 * @return void
		 */
		public function sendMessage(string $text): void {

			$this->bot->sendMessage($text, $this->id);

		}

		/**
		 * Updates the channel's details.
		 *
		 * @param string $name The new channel name.
		 * @param string $type The new channel type.
		 * @return void
		 */
		public function update(string $name, string $type): void {

			$this->name = $name;
			$this->type = $type;

		}

	}

?>