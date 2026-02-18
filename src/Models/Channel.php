<?php

	namespace Sharkord\Models;

	use Sharkord\Sharkord;

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
		 * Channel constructor.
		 *
		 * @param int      $id         The unique channel ID.
		 * @param string   $name       The channel name.
		 * @param string   $type       The channel type (e.g., 'TEXT').
		 * @param int|null $categoryId The ID of the category this channel belongs to.
		 * @param Sharkord $bot        Reference to the main bot instance.
		 */
		public function __construct(
			public int $id,
			public string $name,
			public string $type,
			public ?int $categoryId,
			private Sharkord $bot,
		) {}
		
		public static function fromArray(array $raw, ?Sharkord $bot = null): self {
			return new self(
				$raw['id'],
				$raw['name'],
				$raw['type'] ?? 'TEXT',
				$raw['categoryId'] ?? null,
				$bot
			);
		}
		
		public function updateFromArray(array $raw): void {
			
			if (isset($raw['name'])) $this->name = $raw['name'];
			if (isset($raw['type'])) $this->type = $raw['type'];
			if (isset($raw['categoryId'])) $this->categoryId = $raw['categoryId'];
			
		}

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
		 * Magic getter to access the Category object.
		 *
		 * @param string $name Property name.
		 * @return mixed
		 */
		public function __get(string $name): mixed {
			
			if ($name === 'category' && $this->categoryId) {
				// Access the category manager via the bot instance
				return $this->bot->categories->get($this->categoryId);
			}
			return null;
			
		}

	}
	
?>