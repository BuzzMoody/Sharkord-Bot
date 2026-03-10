<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Models\Message;

	use React\Promise\PromiseInterface;

	/**
	 * Class MessageManager
	 *
	 * Manages message API interactions such as getting messages.
	 *
	 * @package Sharkord\Managers
	 */
	class MessageManager {

		/**
		 * MessageManager constructor.
		 *
		 * @param Sharkord $sharkord The main bot instance.
		 */
		public function __construct(private readonly Sharkord $sharkord) {}

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
				],
				"path" => "messages.get"
			])->then(function ($response) use ($messageId) {

				$messages     = $response['data']['messages'] ?? [];
				$normalizedId = (string) $messageId;

				if (isset($messages[20]) && (string) $messages[20]['id'] === $normalizedId) {
					return Message::fromArray($messages[20], $this->sharkord);
				}

				foreach ($messages as $raw) {
					if ((string) $raw['id'] === $normalizedId) {
						return Message::fromArray($raw, $this->sharkord);
					}
				}

				throw new \RuntimeException(
					"Message ID {$messageId} was not found in the server response."
				);

			});

		}

	}
	
?>