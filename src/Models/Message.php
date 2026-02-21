<?php

	declare(strict_types=1);

	namespace Sharkord\Models;
	
	use Sharkord\Sharkord;

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
			public Sharkord $sharkord,
			public int $id,
			public string $content
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
		
		/**
		 * Magic getter for dynamic properties.
		 *
		 * @param string $name Property name.
		 * @return mixed
		 */
		public function __get(string $name): mixed {
			
			// 1. Handle the request for the server!
			if ($name === 'server' && !empty($this->attributes['serverId'])) {
				// We use the bot instance to ask the ServerManager for the server object
				return $this->sharkord->servers->get($this->attributes['serverId']);
			}
			
			// 2. Handle a request for the channel (if you want $message->channel to work!)
			if ($name === 'channel' && !empty($this->attributes['channelId'])) {
				return $this->sharkord->channels->get($this->attributes['channelId']);
			}

            // 3. Handle a request for the user who sent it
			if ($name === 'author' && !empty($this->attributes['userId'])) {
				return $this->sharkord->users->get($this->attributes['userId']);
			}

			// Otherwise, look inside our magic backpack!
			return $this->attributes[$name] ?? null;
			
		}

	}

?>