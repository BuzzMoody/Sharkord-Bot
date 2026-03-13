<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Permission;
	use Sharkord\ChannelType;
	use Sharkord\Internal\GuardedAsync;
	use Sharkord\Collections\Channels as ChannelsCollection;
	use Sharkord\Models\Channel;
	use React\Promise\PromiseInterface;

	/**
	 * Class ChannelManager
	 *
	 * Manages channel lifecycle events and exposes actions for creating channels.
	 * Delegates all cache storage to a Channels collection instance.
	 *
	 * Accessible via `$sharkord->channels`.
	 *
	 * @package Sharkord\Managers
	 *
	 * @example
	 * ```php
	 * // Create a new text channel
	 * $sharkord->channels->add('announcements', \Sharkord\ChannelType::TEXT, 3)
	 *     ->then(function(\Sharkord\Models\Channel $channel) {
	 *         echo "Created #{$channel->name} (ID: {$channel->id})\n";
	 *     });
	 *
	 * // Look up a cached channel and edit it
	 * $sharkord->channels->get('announcements')?->edit('news', 'Official news feed.');
	 *
	 * // Delete a channel by name
	 * $sharkord->channels->get('old-channel')?->delete();
	 * ```
	 */
	class ChannelManager {

		use GuardedAsync;

		private ChannelsCollection $cache;

		/**
		 * ChannelManager constructor.
		 *
		 * @param Sharkord $sharkord The main bot instance.
		 */
		public function __construct(
			private readonly Sharkord $sharkord
		) {
			$this->cache = new ChannelsCollection($this->sharkord);
		}

		// -------------------------------------------------------------------------
		// Internal event handlers
		// -------------------------------------------------------------------------

		/**
		 * Handles the hydration of a channel from the initial join payload.
		 *
		 * @internal
		 * @param array $raw The raw channel data.
		 * @return void
		 */
		public function hydrate(array $raw): void {

			$this->cache->add($raw);

		}

		/**
		 * Handles the server-initiated creation of a channel.
		 *
		 * Adds the channel to the local cache and emits a `channelcreate` event.
		 *
		 * @internal
		 * @param array $raw The raw channel data.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::CHANNEL_CREATE, function(\Sharkord\Models\Channel $channel): void {
		 *     echo "Channel #{$channel->name} was created.\n";
		 * });
		 * ```
		 */
		public function onCreate(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot create channel: missing 'id' in data.");
				return;
			}

			$this->cache->add($raw);

			$this->sharkord->emit('channelcreate', [$this->cache->get($raw['id'])]);

		}

		/**
		 * Handles a server-pushed update to a channel.
		 *
		 * Updates the cached model in place and emits a `channelupdate` event.
		 *
		 * @internal
		 * @param array $raw The raw channel data.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::CHANNEL_UPDATE, function(\Sharkord\Models\Channel $channel): void {
		 *     echo "Channel #{$channel->name} was updated.\n";
		 * });
		 * ```
		 */
		public function onUpdate(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot update channel: missing 'id' in data.");
				return;
			}

			$channel = $this->cache->update($raw);

			if ($channel) {
				$this->sharkord->emit('channelupdate', [$channel]);
			}

		}

		/**
		 * Handles the server-initiated deletion of a channel.
		 *
		 * Emits a `channeldelete` event with the cached model before removing it.
		 *
		 * @internal
		 * @param int $id The ID of the deleted channel.
		 * @return void
		 *
		 * @example
		 * ```php
		 * $sharkord->on(\Sharkord\Events::CHANNEL_DELETE, function(\Sharkord\Models\Channel $channel): void {
		 *     echo "Channel #{$channel->name} was deleted.\n";
		 * });
		 * ```
		 */
		public function onDelete(int $id): void {

			$channel = $this->cache->get($id);

			if (!$channel) {
				$this->sharkord->logger->error("Channel ID {$id} doesn't exist, therefore cannot be deleted.");
				return;
			}

			$this->sharkord->emit('channeldelete', [$channel]);
			$this->cache->remove($id);

		}

		// -------------------------------------------------------------------------
		// Public API
		// -------------------------------------------------------------------------

		/**
		 * Creates a new channel on the server.
		 *
		 * Sends the channels.add mutation, then immediately fetches the full channel
		 * data via channels.get and hydrates it into the local cache. The returned
		 * Channel model is ready for use without waiting for a server-pushed event.
		 *
		 * Requires the MANAGE_CHANNELS permission.
		 *
		 * @param string      $name       The name for the new channel.
		 * @param ChannelType $type       The channel type. Defaults to TEXT.
		 * @param int|null    $categoryId The ID of the category to place the channel in, or null.
		 * @return PromiseInterface Resolves with the new Channel model, rejects on failure.
		 *
		 * @example
		 * ```php
		 * // Create a basic text channel
		 * $sharkord->channels->add('bot-logs')->then(function(\Sharkord\Models\Channel $channel) {
		 *     $channel->sendMessage("Channel ready.");
		 * });
		 *
		 * // Create a voice channel inside a specific category
		 * $sharkord->channels->add('Voice Chat', \Sharkord\ChannelType::VOICE, 3);
		 * ```
		 */
		public function add(
			string $name,
			ChannelType $type = ChannelType::TEXT,
			?int $categoryId = null,
		): PromiseInterface {

			return $this->guardedAsync(function () use ($name, $type, $categoryId) {

				$this->sharkord->guard->requirePermission(Permission::MANAGE_CHANNELS);

				$input = [
					"type" => $type->value,
					"name" => $name,
				];

				if ($categoryId !== null) {
					$input['categoryId'] = $categoryId;
				}

				return $this->sharkord->gateway->sendRpc("mutation", [
					"input" => $input,
					"path"  => "channels.add",
				])->then(function (array $response) {

					$channelId = $response['data']
						?? throw new \RuntimeException(
							"channels.add response missing 'data' (expected new channel ID)."
						);

					return $this->sharkord->gateway->sendRpc("query", [
						"input" => ["channelId" => (int) $channelId],
						"path"  => "channels.get",
					])->then(function (array $response) use ($channelId) {

						$raw = $response['data']
							?? throw new \RuntimeException(
								"channels.get response missing 'data' for channel ID {$channelId}."
							);

						$this->cache->add($raw);

						return $this->cache->get((int) $channelId)
							?? throw new \RuntimeException(
								"Channel ID {$channelId} was not found in cache after add()."
							);

					});

				});

			});

		}

		/**
		 * Retrieves a cached channel by ID or name.
		 *
		 * @param int|string $idOrName The channel ID (int) or name (string).
		 * @return Channel|null The cached Channel model, or null if not found.
		 *
		 * @example
		 * ```php
		 * $channel = $sharkord->channels->get('general');
		 * $channel?->sendMessage("Hello!");
		 *
		 * $channel = $sharkord->channels->get(42);
		 * $channel?->sendMessage("Hello by ID!");
		 * ```
		 */
		public function get(int|string $idOrName): ?Channel {

			return $this->cache->get($idOrName);

		}

		/**
		 * Returns the number of channels currently held in the cache.
		 *
		 * @return int
		 *
		 * @example
		 * ```php
		 * echo "Cached channels: " . $sharkord->channels->count() . "\n";
		 * ```
		 */
		public function count(): int {

			return count($this->cache);

		}

		/**
		 * Returns the underlying Channels collection.
		 *
		 * @return ChannelsCollection
		 *
		 * @example
		 * ```php
		 * foreach ($sharkord->channels->collection() as $id => $channel) {
		 *     echo "#{$channel->name}\n";
		 * }
		 * ```
		 */
		public function collection(): ChannelsCollection {

			return $this->cache;

		}

	}

?>