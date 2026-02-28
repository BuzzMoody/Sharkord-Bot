<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use React\Promise\PromiseInterface;

	/**
	 * Class MessageManager
	 *
	 * Manages message caching and API fetching.
	 */
	class MessageManager {

		public function __construct(private Sharkord $sharkord) {}
		
		/**
		 * Edits a message directly by its ID without requiring it to be cached.
		 *
		 * @param int|string $messageId The ID of the message to edit.
		 * @param string $newContent The new message text.
		 * @return PromiseInterface Resolves with true on success.
		 */
		public function editMessage(int|string $messageId, string $newContent): PromiseInterface {
			
			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => ["messageId" => $messageId, "content" => $newContent], 
				"path"  => "messages.edit"
			])->then(function($response) {
				
				if (isset($response['type']) && $response['type'] === 'data') {
					return true;
				}
				
				throw new \RuntimeException("Failed to edit message. Server responded with: " . json_encode($response));
			});
			
		}

		/**
		 * Deletes a message directly by its ID without requiring it to be cached.
		 *
		 * @param int|string $messageId The ID of the message to delete.
		 * @return PromiseInterface Resolves with true on success.
		 */
		public function deleteMessage(int|string $messageId): PromiseInterface {
			
			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => ["messageId" => $messageId], 
				"path"  => "messages.delete"
			])->then(function($response) {
				
				if (isset($response['type']) && $response['type'] === 'data') {
					return true;
				}
				
				throw new \RuntimeException("Failed to delete message. Server responded with: " . json_encode($response));
				
			});
			
		}

	}
?>