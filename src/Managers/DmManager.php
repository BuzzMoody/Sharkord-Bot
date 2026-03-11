<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Models\Channel;
	use Sharkord\Models\DirectMessage;
	use React\Promise\PromiseInterface;

	/**
	 * Class DmManager
	 *
	 * Manages direct message interactions with the Sharkord API.
	 * Accessible via $sharkord->dms.
	 *
	 * @package Sharkord\Managers
	 *
	 * @example
	 * ```php
	 * // Send a DM directly from a User object
	 * $user->sendDm("Hey there!");
	 *
	 * // Open a DM channel and use it directly
	 * $user->openDm()->then(function(Channel $channel) {
	 *     $channel->sendMessage("Hello from the channel object!");
	 *     $channel->markAsRead();
	 * });
	 *
	 * // List all existing DM threads
	 * $sharkord->dms->get()->then(function(array $dms) {
	 *     foreach ($dms as $dm) {
	 *         echo "{$dm->user->name}: {$dm->unreadCount} unread\n";
	 *         $dm->markRead();
	 *     }
	 * });
	 *
	 * // Open a DM channel by user ID
	 * $sharkord->dms->open(5)->then(function(Channel $channel) {
	 *     $channel->sendMessage("Hello!");
	 * });
	 * ```
	 */
	class DmManager {

		/**
		 * DmManager constructor.
		 *
		 * @param Sharkord $sharkord The main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {}

		/**
		 * Opens a DM channel with the specified user.
		 *
		 * If the DM channel does not yet exist on the server it will be created.
		 * The resolved Channel may not be present in the channel cache if this is
		 * the first time a DM has been opened with this user — in that case the
		 * returned Channel is constructed directly from the response data.
		 *
		 * @param int $userId The ID of the user to open a DM with.
		 * @return PromiseInterface Resolves with a Channel object, rejects on failure.
		 *
		 * @example
		 * ```php
		 * $sharkord->dms->open(5)->then(function(Channel $channel) {
		 *     $channel->sendMessage("Hello!");
		 * });
		 * ```
		 */
		public function open(int $userId): PromiseInterface {

			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => ["userId" => $userId],
				"path"  => "dms.open",
			])->then(function (array $response) {

				$channelId = $response['data']['channelId']
					?? throw new \RuntimeException("dms.open response missing 'channelId'.");

				$channel = $this->sharkord->channels->get((int) $channelId);

				if ($channel !== null) {
					return $channel;
				}

				// DM channels may not be pre-cached; construct a minimal Channel from the ID
				// so the caller can immediately send messages without an additional lookup.
				return Channel::fromArray(['id' => (int) $channelId], $this->sharkord);

			});

		}

		/**
		 * Retrieves all DM threads the bot currently has open.
		 *
		 * @return PromiseInterface Resolves with an array of DirectMessage objects, rejects on failure.
		 *
		 * @example
		 * ```php
		 * $sharkord->dms->get()->then(function(array $dms) {
		 *     foreach ($dms as $dm) {
		 *         echo "{$dm->user->name} — last message: {$dm->lastMessageAt}\n";
		 *     }
		 * });
		 * ```
		 */
		public function get(): PromiseInterface {

			return $this->sharkord->gateway->sendRpc("query", [
				"path" => "dms.get",
			])->then(function (array $response) {

				$rawList = $response['data']
					?? throw new \RuntimeException("dms.get response missing 'data'.");

				return array_map(
					fn(array $raw) => DirectMessage::fromArray($raw, $this->sharkord),
					$rawList
				);

			});

		}

	}

?>