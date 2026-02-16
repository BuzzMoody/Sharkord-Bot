<?php

	namespace Sharkord\Models;

	/**
	 * Class Message
	 *
	 * Represents a received chat message.
	 *
	 * @package Sharkord\Models
	 */
	class Message {

		/**
		 * Message constructor.
		 *
		 * @param int     $id      The unique message ID.
		 * @param string  $content The text content of the message.
		 * @param User    $user    The user who sent the message.
		 * @param Channel $channel The channel where the message was sent.
		 */
		public function __construct(
			public int $id,
			public string $content,
			public User $user,
			public Channel $channel
		) {}

		/**
		 * Replies to this message in the same channel.
		 *
		 * @param string $text The reply content.
		 * @return void
		 */
		public function reply(string $text): void {

			$this->channel->sendMessage($text);

		}

	}

?>