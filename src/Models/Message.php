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
		 * @var array Stores all dynamic server data from the API
		 */
		private array $attributes = [];

		/**
		 * Server constructor.
		 *
		 * @param Sharkord $sharkord Reference to the main bot instance.
		 * @param array    $rawData  The raw array of data from the API.
		 */
		public function __construct(
			private Sharkord $sharkord,
			array $rawData
		) {
			$this->updateFromArray($rawData);
		}
		
		/**
		 * Factory method to create a Server from raw API data.
		 */
		public static function fromArray(array $raw, Sharkord $sharkord): self {
			return new self($sharkord, $raw);
		}
		
		/**
		 * Updates the Message's information dynamically.
		 *
		 * @param array $raw The raw Message data.
		 * @return void
		 */
		public function updateFromArray(array $raw): void {
			
			// Preserve your logic to strip HTML tags from the content
			if (isset($raw['content'])) {
				$raw['content'] = strip_tags($raw['content']);
			}

			// Merge the new data into our attributes array
			$this->attributes = array_merge($this->attributes, $raw);
			
		}

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