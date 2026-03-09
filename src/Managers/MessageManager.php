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
		
		/**
		 * Toggles the pinned state of a message directly by its ID.
		 *
		 * Sends the togglePin mutation and waits for the subsequent messages.onUpdate
		 * subscription event to confirm and return the new pinned state.
		 *
		 * @param int|string $messageId The ID of the message to pin or unpin.
		 * @return PromiseInterface Resolves with a bool indicating the new pinned state (true = pinned, false = unpinned).
		 */
		public function togglePin(int|string $messageId): PromiseInterface {

			if (!$this->sharkord->bot) {
				return reject(new \RuntimeException("Bot entity not set."));
			}

			if (!$this->sharkord->bot->hasPermission(\Sharkord\Permission::MANAGE_MESSAGES)) {
				return reject(new \RuntimeException("Missing MANAGE_MESSAGES permission to pin/unpin messages."));
			}

			return new \React\Promise\Promise(function($resolve, $reject) use ($messageId) {

				$this->sharkord->gateway->sendRpc("mutation", [
					"input" => ["messageId" => $messageId],
					"path"  => "messages.togglePin"
				])->then(function($response) use ($resolve, $reject, $messageId) {

					if (!isset($response['type']) || $response['type'] !== 'data') {
						$reject(new \RuntimeException("Failed to toggle pin. Server responded with: " . json_encode($response)));
						return;
					}

					$listener = null;
					$listener = function(\Sharkord\Models\Message $updated) use ($resolve, $messageId, &$listener) {
						if ($updated->id === $messageId) {
							$this->sharkord->removeListener('messageupdate', $listener);
							$resolve((bool)$updated->pinned);
						}
					};

					$this->sharkord->on('messageupdate', $listener);

				})->catch($reject);

			});

		}
		
		/**
		 * Checks whether a message is currently pinned.
		 *
		 * Accepts either a Message object for an immediate local check, or a
		 * message ID which fetches the current state from the server via RPC.
		 *
		 * @param \Sharkord\Models\Message|int|string $message The Message object or message ID.
		 * @return bool|PromiseInterface Returns a bool directly for Message objects, or a PromiseInterface resolving to a bool for ID lookups.
		 */
		public function isPinned(\Sharkord\Models\Message|int|string $message): bool|PromiseInterface {

			// If we already have the object, just read the attribute directly
			if ($message instanceof \Sharkord\Models\Message) {
				return $message->isPinned();
			}

			// Otherwise fetch the current state from the server
			return $this->sharkord->gateway->sendRpc("query", [
				"input" => ["messageId" => $message],
				"path"  => "messages.get"
			])->then(function($response) {
				return (bool)($response['data']['pinned'] ?? false);
			});

		}
		
		/**
		 * Fetches a single message from the server by its ID.
		 *
		 * Uses the messages.get RPC path with targetMessageId to scope the query
		 * to a specific message, requiring the channelId for context.
		 *
		 * @param int|string $messageId The ID of the message to fetch.
		 * @param int|string $channelId The ID of the channel the message belongs to.
		 * @return PromiseInterface Resolves with a Message object, or rejects if not found.
		 */
		public function get(int|string $messageId, int|string $channelId): PromiseInterface {

			return $this->sharkord->gateway->sendRpc("query", [
				"input" => [
					"channelId"       => $channelId,
					"targetMessageId" => $messageId,
					"cursor"          => null,
					"limit"           => 10
				],
				"path" => "messages.get"
			])->then(function($response) use ($messageId) {

				$messages = $response['data'] ?? [];

				foreach ($messages as $raw) {
					if ($raw['id'] === $messageId) {
						return \Sharkord\Models\Message::fromArray($raw, $this->sharkord);
					}
				}

				throw new \RuntimeException("Message ID {$messageId} was not found in the server response.");

			});

		}

	}
?>