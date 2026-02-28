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

		public function __construct(private Sharkord $sharkord) {}
		
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
					return true;
				}
				
				throw new \RuntimeException("Failed to delete message. Server responded with: " . json_encode($response));
				
			});
			
		}

	}
?>