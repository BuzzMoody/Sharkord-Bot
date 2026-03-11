<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Collections\Channels as ChannelsCollection;
	use Sharkord\Models\Channel;

	/**
	 * Class ChannelManager
	 *
	 * Manages channel lifecycle events, delegating all cache storage to a
	 * Channels collection instance.
	 *
	 * @package Sharkord\Managers
	 */
	class ChannelManager {

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

		/**
		 * Handles the hydration of a channel.
		 *
		 * @param array $raw The raw channel data.
		 * @return void
		 */
		public function hydrate(array $raw): void {

			$this->cache->add($raw);

		}

		/**
		 * Handles the creation of a channel.
		 *
		 * @param array $raw The raw channel data.
		 * @return void
		 */
		public function create(array $raw): void {

			if (!isset($raw['id'])) {
				$this->sharkord->logger->warning("Cannot create channel: missing 'id' in data.");
				return;
			}

			$this->cache->add($raw);

			$this->sharkord->emit('channelcreate', [$this->cache->get($raw['id'])]);

		}

		/**
		 * Handles updates to a channel.
		 *
		 * @param array $raw The raw channel data.
		 * @return void
		 */
		public function update(array $raw): void {

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
		 * Handles channel deletion.
		 *
		 * @param int $id The ID of the deleted channel.
		 * @return void
		 */
		public function delete(int $id): void {

			$channel = $this->cache->get($id);

			if (!$channel) {
				$this->sharkord->logger->error("Channel ID {$id} doesn't exist, therefore cannot be deleted.");
				return;
			}

			$this->sharkord->emit('channeldelete', [$channel]);
			$this->cache->remove($id);

		}

		/**
		 * Retrieves a channel by ID or name.
		 *
		 * @param int|string $identifier The channel ID or name.
		 * @return Channel|null
		 */
		public function get(int|string $identifier): ?Channel {

			return $this->cache->get($identifier);

		}

		/**
		 * Returns the underlying Channels collection.
		 *
		 * @return ChannelsCollection
		 */
		public function collection(): ChannelsCollection {

			return $this->cache;

		}

		/**
		 * Returns the count of cached channels.
		 *
		 * @return int
		 */
		public function count(): int {

			return count($this->cache);

		}

	}

?>