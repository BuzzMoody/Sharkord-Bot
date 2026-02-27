<?php

	declare(strict_types=1);

	namespace Sharkord\Models;
	
	use Sharkord\Sharkord;
	use React\Promise\PromiseInterface;
	use function React\Promise\reject;
	use LitEmoji\LitEmoji;
	use Sharkord\Permission;

	/**
	 * Class Message
	 *
	 * Represents a received chat message.
	 *
	 * @package Sharkord\Models
	 */
	class Message {

		/**
		 * @var array Stores all dynamic message data from the API
		 */
		private array $attributes = [];

		/**
		 * Message constructor.
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
		 * Factory method to create a Message from raw API data.
		 */
		public static function fromArray(array $raw, Sharkord $sharkord): self {
			return new self($sharkord, $raw);
		}
		
		/**
		 * Updates the Message's information dynamically.
		 *
		 * @internal This method is for internal framework use only. Do not call this directly.
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
		 * @return PromiseInterface Resolves when the message is sent.
		 */
		public function reply(string $text): PromiseInterface {

			if ($this->channel) {
				return $this->channel->sendMessage($text);
			}
			
			return reject(new \RuntimeException("Channel not found for this message."));

		}
		
		/**
		 * Adds or toggles an emoji reaction on a specific message.
		 *
		 * @param string  $emoji   The emoji character(s) to use for the reaction.
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function react(string $emoji): PromiseInterface {
			
			if (!$this->sharkord->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}
			
			if (!$this->sharkord->bot->hasPermission(Permission::REACT_TO_MESSAGES)) {
				return reject(new \RuntimeException("Missing REACT_TO_MESSAGES permission."));
			}
			
			if (!$this->isEmoji($emoji)) {
				return reject(new \InvalidArgumentException("Invalid emoji provided: '{$emoji}'"));
			}
			
			$emojiText = $this->emojiToText($emoji);
			
			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => ["messageId" => $this->id, "emoji" => $emojiText], 
				"path" => "messages.toggleReaction"
			])->then(function($response) use () {
				
				if (isset($response['type']) && $response['type'] === 'data') {
					return true;
				}
				
				throw new \RuntimeException("Failed to react to message. Server responded with: " . json_encode($response));
			});
			
		}
		
		/**
		 * Edits the content of this message.
		 *
		 * @param string $newContent The new message text.
		 * @return PromiseInterface Resolves when the message is edited.
		 */
		public function edit(string $newContent): PromiseInterface {
			
			if (!$this->sharkord->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}
			
			$isOwnMessage = ($this->author && $this->author->id === $this->sharkord->bot->id);
			
			// If it's not our message, we need MANAGE_MESSAGES permission
			if (!$isOwnMessage && !$this->sharkord->bot->hasPermission(\Sharkord\Permission::MANAGE_MESSAGES)) {
				return reject(new \RuntimeException("Missing MANAGE_MESSAGES permission to edit other users' messages."));
			}

			// Pass the ID and content to our efficient MessageManager
			return $this->sharkord->messages->editMessage($this->id, $newContent);
			
		}

		/**
		 * Deletes this message.
		 *
		 * @return PromiseInterface Resolves when the message is deleted.
		 */
		public function delete(): PromiseInterface {
			
			if (!$this->sharkord->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}

			$isOwnMessage = ($this->author && $this->author->id === $this->sharkord->bot->id);
			
			// If it's not our message, we need MANAGE_MESSAGES permission
			if (!$isOwnMessage && !$this->sharkord->bot->hasPermission(\Sharkord\Permission::MANAGE_MESSAGES)) {
				return reject(new \RuntimeException("Missing MANAGE_MESSAGES permission to delete other users' messages."));
			}

			// Pass the ID to our efficient MessageManager
			return $this->sharkord->messages->deleteMessage($this->id);
			
		}
		
		/**
		 * Validates whether a given string is exactly one single emoji.
		 *
		 * @param string $emoji The string to validate.
		 * @return bool Returns true if the string is exactly one valid emoji sequence, false otherwise.
		 */
		private function isEmoji(string $emoji): bool {
			
			$pattern = '/^\p{Extended_Pictographic}[\x{FE0F}\p{M}\x{1F3FB}-\x{1F3FF}]*(?:\x{200D}\p{Extended_Pictographic}[\x{FE0F}\p{M}\x{1F3FB}-\x{1F3FF}]*)*$/u';
			return preg_match($pattern, $emoji) === 1;
			
		}
		
		/**
		 * Turns the visual emoji into the text name
		 *
		 * @param string $emoji The emoji to convert.
		 * @return string Returns the text string value of the emoji
		 */
		private function emojiToText(string $emoji): string {
			
			$unicodeName = LitEmoji::encodeShortcode($emoji);
			return str_replace(array(' ', ':'), array('_', ''), strtolower($unicodeName));
			
		}
		
		/**
		 * Returns a complete array of the message data, including 
		 * fully expanded User, Channel, and Server objects for debugging.
		 *
		 * @return array
		 */
		public function toArray(): array {
			
			// 1. Grab the base message data
			$debugData = $this->attributes;

			// 2. If a channel exists, fetch it and turn it into an array
			if ($this->channel) {
				$debugData['channel_expanded'] = $this->channel->toArray();
			}

			// 3. If a user exists, fetch them and turn them into an array
			if ($this->author) {
				$debugData['user_expanded'] = $this->author->toArray();
			}

			// 4. If a server exists, fetch it and turn it into an array
			if ($this->server) {
				$debugData['server_expanded'] = $this->server->toArray();
			}

			return $debugData;
			
		}
		
		/**
		 * Magic getter for dynamic properties.
		 *
		 * @param string $name Property name.
		 * @return mixed
		 */
		public function __get(string $name): mixed {
			
			// 1. Handle the request for the server!
			if ($name === 'server') {
				// We use the bot instance to ask the ServerManager for the server object
				return $this->sharkord->servers->getFirst();
			}
			
			// 2. Handle a request for the channel (if you want $message->channel to work!)
			if ($name === 'channel' && !empty($this->attributes['channelId'])) {
				return $this->sharkord->channels->get($this->attributes['channelId']);
			}

			// 3. Handle a request for the user who sent it
			if (($name === 'author' || $name === 'user') && !empty($this->attributes['userId'])) {
				return $this->sharkord->users->get($this->attributes['userId']);
			}

			// Otherwise, look inside our magic backpack!
			return $this->attributes[$name] ?? null;
			
		}

	}

?>