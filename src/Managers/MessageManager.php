<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Models\Message;
	use React\Promise\PromiseInterface;
	use function React\Promise\resolve;

	/**
	 * Class MessageManager
	 *
	 * Manages message caching and API fetching.
	 */
	class MessageManager {

		private array $messages = [];
		private int $cacheLimit = 200; // Prevent memory leaks

		public function __construct(private Sharkord $sharkord) {}

		/**
		 * Adds a message to the cache.
		 */
		public function cache(Message $message): void {
			$this->messages[$message->id] = $message;

			// Keep cache from growing infinitely
			if (count($this->messages) > $this->cacheLimit) {
				array_shift($this->messages);
			}
		}

		/**
		 * Retrieves a message by ID from cache, or fetches it from the API.
		 *
		 * @param string $id The Message ID
		 * @return PromiseInterface Resolves with the Message object
		 */
		public function fetch(string $id): PromiseInterface {
			
			// 1. Check local cache first
			if (isset($this->messages[$id])) {
				return resolve($this->messages[$id]);
			}

			// 2. Fetch from the Sharkord API via Gateway RPC if not cached
			return $this->sharkord->gateway->sendRpc("query", [
				"input" => ["messageId" => $id],
				"path"  => "messages.get" // Assuming this is your API path
			])->then(function($raw) {
				$message = Message::fromArray($raw['data'], $this->sharkord);
				$this->cache($message);
				return $message;
			});
		}
		
		/**
		 * Edits a message directly by its ID without requiring it to be cached.
		 *
		 * @param string $messageId The ID of the message to edit.
		 * @param string $newContent The new message text.
		 * @return PromiseInterface Resolves with true on success.
		 */
		public function editMessage(int $messageId, string $newContent): PromiseInterface {
			
			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => ["messageId" => $messageId, "content" => $newContent], 
				"path"  => "messages.edit"
			])->then(function($response) use ($messageId, $newContent) {
				
				if (isset($response['type']) && $response['type'] === 'data') {
					// If the message happens to be in our local cache, update it so it stays accurate
					if (isset($this->messages[$messageId])) {
						$this->messages[$messageId]->attributes['content'] = $newContent;
					}
					return true;
				}
				
				throw new \RuntimeException("Failed to edit message. Server responded with: " . json_encode($response));
			});
			
		}

		/**
		 * Deletes a message directly by its ID without requiring it to be cached.
		 *
		 * @param string $messageId The ID of the message to delete.
		 * @return PromiseInterface Resolves with true on success.
		 */
		public function deleteMessage(int $messageId): PromiseInterface {
			
			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => ["messageId" => $messageId], 
				"path"  => "messages.delete"
			])->then(function($response) use ($messageId) {
				
				if (isset($response['type']) && $response['type'] === 'data') {
					// If the message happens to be in our local cache, update it so it stays accurate
					if (isset($this->messages[$messageId])) {
						unset($this->messages[$messageId]);
					}
					
					return true;
				}
				
				throw new \RuntimeException("Failed to delete message. Server responded with: " . json_encode($response));
				
			});
			
		}

	}
?>