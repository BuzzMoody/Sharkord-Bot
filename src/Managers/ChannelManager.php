<?php

	declare(strict_types=1);

	namespace Sharkord\Managers;

	use Sharkord\Sharkord;
	use Sharkord\Models\Channel;

	/**
	 * Class ChannelManager
	 *
	 * Manages the state, creation, updating, and deletion of channels.
	 *
	 * @package Sharkord\Managers
	 */
	class ChannelManager {

		/**
		 * ChannelManager constructor.
		 *
		 * @param Sharkord $bot The main bot instance (required for Channel actions).
		 * @param array<int, Channel> $channels Cache of Channel models indexed by ID.
		 */
		public function __construct(
			private Sharkord $bot,
			private array $channels = []
		) {}

		/**
		 * Handles the creation of a channel (or hydration from initial cache).
		 *
		 * @param array $raw The raw channel data.
		 * @return void
		 */
		public function handleCreate(array $raw): void {
			
			$channel = Channel::fromArray($raw, $this->bot);
			
			$this->channels[$raw['id']] = $channel;
			$this->bot->logger->info("New channel created: {$channel->name}");
			
		}

		/**
		 * Handles the deletion of a channel.
		 *
		 * @param int $id The ID of the deleted channel.
		 * @return void
		 */
		public function handleDelete(int $id): void {
			
			if (isset($this->channels[$id])) {
				$this->bot->logger->info("Channel deleted: {$this->channels[$id]->name}");
				unset($this->channels[$id]);
			}
			
		}

		/**
		 * Handles the update of channel details.
		 *
		 * @param array $raw The raw channel data.
		 * @return void
		 */
		public function handleUpdate(array $raw): void {
			
			if (isset($this->channels[$raw['id']])) {
				
				$this->channels[$raw['id']]->updateFromArray($raw);
				$this->bot->logger->info("Channel updated: {$this->channels[$raw['id']]->name}");
				
			}
			
		}
		
		/**
		 * Retrieves a channel by ID.
		 *
		 * @param int $id The channel ID.
		 * @return Channel|null Returns the Channel object or null if not found.
		 */
		public function get(int $id): ?Channel {
			
			return $this->channels[$id] ?? null;
			
		}

		/**
		 * Returns the count of cached channels.
		 * * @return int
		 */
		public function count(): int {
			
			return count($this->channels);
			
		}

	}
	
?>