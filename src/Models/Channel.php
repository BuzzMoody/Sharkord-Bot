<?php

	declare(strict_types=1);

	namespace Sharkord\Models;

	use Sharkord\Sharkord;
	use React\Promise\PromiseInterface;

	/**
	 * Class Channel
	 *
	 * Represents a chat channel on the server.
	 *
	 * @property-read Category|null $category The category this channel belongs to.
	 * @package Sharkord\Models
	 */
	class Channel {

		/**
		 * @var array Stores all dynamic channel data from the API
		 */
		private array $attributes = [];

		/**
		 * Channel constructor.
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
		 * Factory method to create a Channel from raw API data.
		 */
		public static function fromArray(array $raw, Sharkord $sharkord): self {
			return new self($sharkord, $raw);
		}
		
		/**
		 * Updates the channel's information dynamically.
		 *
		 * @internal This method is for internal framework use only. Do not call this directly.
		 * @param array $raw The raw channel data from the server.
		 * @return void
		 */
		public function updateFromArray(array $raw): void {
			
			$this->attributes = array_merge($this->attributes, $raw);
			
		}
		
		/**
		 * Sends a message to a specific channel.
		 *
		 * @param string $text The message content.
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function sendMessage(string $text): PromiseInterface {

			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => [
					"content" => "<p>".htmlspecialchars($text)."</p>", 
					"channelId" => $this->id, 
					"files" => []
				], 
				"path" => "messages.send"
			]);

		}
		
		/**
		 * Returns all the attributes as an array. Perfect for debugging!
		 *
		 * @return array
		 */
		public function toArray(): array {
			
			return $this->attributes;
			
		}

		/**
		 * Magic getter. This is triggered whenever you try to access a property 
		 * that isn't explicitly defined (e.g., $channel->topic or $channel->position).
		 *
		 * @param string $name Property name.
		 * @return mixed
		 */
		public function __get(string $name): mixed {
			
			// Handle the special 'category' relationship request
			if ($name === 'category' && !empty($this->attributes['categoryId'])) {
				// Access the category manager via the bot instance
				return $this->sharkord->categories->get($this->attributes['categoryId']);
			}

			// If it's not 'category', look inside our magic backpack!
			return $this->attributes[$name] ?? null;
			
		}

	}
	
?>