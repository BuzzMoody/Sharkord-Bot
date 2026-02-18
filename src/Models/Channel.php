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
		 * @param Sharkord $bot        Reference to the main bot instance.
		 * @param int|null $categoryId The ID of the category this channel belongs to.
		 */
		public function __construct(
			public int $id,
			public string $name,
			public string $type,
			private Sharkord $bot,
			public ?int $categoryId = null
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
		 * @param string   $name       The new channel name.
		 * @param string   $type       The new channel type.
		 * @param int|null $categoryId The new category ID.
		 * @return void
		 */
		public function update(string $name, string $type, ?int $categoryId = null): void {

			$this->name = $name;
			$this->type = $type;
			$this->categoryId = $categoryId;

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