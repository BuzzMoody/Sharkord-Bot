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
		 * @param Sharkord $sharkord The main bot instance (required for Channel actions).
		 * @param array<int, Channel> $channels Cache of Channel models indexed by ID.
		 */
		public function __construct(
			private Sharkord $sharkord,
			private array $channels = []
		) {}


		/**
		 * Handles the hydration of a channel.
		 *
		 * @param array $raw The raw channel data.
		 * @return void
		 */
		public function hydrate(array $raw): void {
			
			$channel = Channel::fromArray($raw, $this->sharkord);
			$this->channels[$raw['id']] = $channel;
			
		}
		
		/**
		 * Handles the creation of a channel.
		 *
		 * @param array $raw The raw channel data.
		 * @return void
		 */
		public function create(array $raw): void {
			
			$channel = Channel::fromArray($raw, $this->sharkord);
			$this->channels[$raw['id']] = $channel;
			
			$this->sharkord->emit('channelcreate', [$channel]);
			
		}
		
		/**
		 * Handles updates to a channel.
		 *
		 * @param array $raw The raw category data.
		 * @return void
		 */		
		public function update(array $raw): void {
			
			if (isset($this->channels[$raw['id']])) {
				
				$this->channels[$raw['id']]->updateFromArray($raw);
				
				$this->sharkord->emit('channelupdate', [$this->channels[$raw['id']]]);
				
			}
			
		}

		/**
		 * Handles channel deletion.
		 *
		 * @param int $id The ID of the deleted category.
		 * @return void
		 */
		 public function delete(int $id): void {
			
			if (!isset($this->channels[$id])) { 
				$this->logger->error("Channel ID {$id} doesn't exist, therefore cannot be deleted.");
				return;
			}
			
			$this->sharkord->emit('channeldelete', [$this->channels[$id]]);
			
			unset($this->channels[$id]);
			
		}
		
		/**
		 * Retrieves a channel by ID or name.
		 *
		 * @param int|string $identifier The channel ID or name.
		 * @return Channel|null Returns the Channel object or null if not found.
		 */
		public function get(int|string $identifier): ?Channel {
			
			if (is_int($identifier)) {
				
				return $this->channels[$identifier] ?? null;
				
			}
			
			foreach ($this->channels as $channel) {
				
				if ($channel->name === $identifier) {
					
					return $channel;
					
				}
				
			}
			
			return null;
			
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