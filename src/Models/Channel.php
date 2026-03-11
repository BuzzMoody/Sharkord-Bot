<?php

	declare(strict_types=1);

	namespace Sharkord\Models;

	use Sharkord\Sharkord;
	use Sharkord\Internal\PromiseUtils;
	use React\Promise\PromiseInterface;

	/**
	 * Class Channel
	 *
	 * Represents a chat channel on the server.
	 *
	 * @property-read Category|null $category The category this channel belongs to.
	 * @package Sharkord\Models
	 */
	class Channel {

		/**
		 * @var array Stores all dynamic channel data from the API
		 */
		private array $attributes = [];

		/**
		 * Channel constructor.
		 *
		 * @param Sharkord $sharkord Reference to the main bot instance.
		 * @param array    $rawData  The raw array of data from the API.
		 */
		public function __construct(
			private Sharkord $sharkord,
			array $rawData
		) {
			$this->updateFromArray($rawData);
		}
		
		/**
		 * Factory method to create a Channel from raw API data.
		 */
		public static function fromArray(array $raw, Sharkord $sharkord): self {
			return new self($sharkord, $raw);
		}
		
		/**
		 * Updates the channel's information dynamically.
		 *
		 * @internal This method is for internal framework use only. Do not call this directly.
		 * @param array $raw The raw channel data from the server.
		 * @return void
		 */
		public function updateFromArray(array $raw): void {
			
			$this->attributes = array_merge($this->attributes, $raw);
			
		}
		
		/**
		 * Sends a message to a specific channel.
		 *
		 * @param string $text The message content.
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function sendMessage(string $text): PromiseInterface {

			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => [
					"content" => "<p>".htmlspecialchars($text)."</p>", 
					"channelId" => $this->id, 
					"files" => []
				], 
				"path" => "messages.send"
			])->then(function($response) {
				if (isset($response['type']) && $response['type'] === 'data') {
					return true;
				}
				throw new \RuntimeException("Failed to send message. Server responded with: " . json_encode($response));
			});

		}
		
		/**
		 * Sends a pre-built HTML string to the channel without escaping.
		 *
		 * Intended for internal framework use where the content has already been
		 * constructed as safe HTML (e.g., mention spans). For plain text, use
		 * sendMessage() instead.
		 *
		 * @internal
		 * @param string $html The raw HTML content string.
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function sendRawMessage(string $html): PromiseInterface {

			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => [
					"content"   => "<p>{$html}</p>",
					"channelId" => $this->id,
					"files"     => []
				],
				"path" => "messages.send"
			])->then(function($response) {
				if (isset($response['type']) && $response['type'] === 'data') {
					return true;
				}
				throw new \RuntimeException("Failed to send message. Server responded with: " . json_encode($response));
			});

		}
		
		/**
		 * Sends a single typing indicator signal to this channel.
		 *
		 * The indicator will be visible to other users for approximately 800ms.
		 * For longer operations, use sendTypingWhile() instead.
		 *
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 */
		public function sendTyping(): PromiseInterface {

			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => ["channelId" => $this->id],
				"path"  => "messages.signalTyping"
			])->then(function ($response) {

				if (isset($response['type']) && $response['type'] === 'data') {
					return true;
				}

				throw new \RuntimeException(
					"Failed to send typing indicator. Server responded with: " . json_encode($response)
				);

			});

		}

		/**
		 * Sends a repeating typing indicator for the duration of a pending Promise.
		 *
		 * Fires a typing signal immediately and then every 700ms until the given
		 * Promise resolves or rejects, ensuring the indicator stays visible throughout.
		 * The result or rejection of the given Promise is passed through transparently.
		 *
		 * @param PromiseInterface $promise The operation to show a typing indicator for.
		 * @return PromiseInterface Resolves or rejects with the same value as the given Promise.
		 */
		public function sendTypingWhile(PromiseInterface $promise): PromiseInterface {

			$sendTypingSafe = function () {
				$this->sendTyping()->catch(function (mixed $reason) {
					$this->sharkord->logger->warning(
						"Typing indicator failed: " . PromiseUtils::reasonToString($reason)
					);
				});
			};

			$sendTypingSafe();

			$timer = $this->sharkord->loop->addPeriodicTimer(0.7, $sendTypingSafe);

			$stop = function () use ($timer) {
				$this->sharkord->loop->cancelTimer($timer);
			};

			return $promise->then(
				function ($value) use ($stop) {
					$stop();
					return $value;
				},
				function (mixed $reason) use ($stop) {
					$stop();
					throw $reason instanceof \Throwable
						? $reason
						: new \RuntimeException(PromiseUtils::reasonToString($reason));
				}
			);

		}
		
		/**
		 * Marks all messages in this channel (or DM thread) as read.
		 *
		 * @return PromiseInterface Resolves on success, rejects on failure.
		 *
		 * @example
		 * ```php
		 * $user->openDm()->then(function(Channel $channel) {
		 *     $channel->markAsRead();
		 * });
		 *
		 * // Or after retrieving a channel by name
		 * $sharkord->channels->get('general')->markAsRead();
		 * ```
		 */
		public function markAsRead(): PromiseInterface {

			return $this->sharkord->gateway->sendRpc("mutation", [
				"input" => ["channelId" => $this->id],
				"path"  => "channels.markAsRead",
			])->then(function ($response) {

				if (isset($response['type']) && $response['type'] === 'data') {
					return true;
				}

				throw new \RuntimeException(
					"Failed to mark channel as read. Server responded with: " . json_encode($response)
				);

			});

		}
		
		/**
		 * Returns all the attributes as an array. Perfect for debugging!
		 *
		 * @return array
		 */
		public function toArray(): array {
			
			return $this->attributes;
			
		}
		
		/**
		 * Magic isset check. Allows isset() and empty() to work correctly
		 * against both stored attributes and virtual relational properties.
		 *
		 * @param string $name Property name.
		 * @return bool
		 */
		public function __isset(string $name): bool {

			return match($name) {
				'category' => !empty($this->attributes['categoryId']) && $this->sharkord->categories->get($this->attributes['categoryId']) !== null,
				default    => isset($this->attributes[$name]),
			};

		}

		/**
		 * Magic getter. This is triggered whenever you try to access a property 
		 * that isn't explicitly defined (e.g., $channel->topic or $channel->position).
		 *
		 * @param string $name Property name.
		 * @return mixed
		 */
		public function __get(string $name): mixed {
			
			// Handle the special 'category' relationship request
			if ($name === 'category' && !empty($this->attributes['categoryId'])) {
				// Access the category manager via the bot instance
				return $this->sharkord->categories->get($this->attributes['categoryId']);
			}

			// If it's not 'category', look inside our magic backpack!
			return $this->attributes[$name] ?? null;
			
		}

	}
	
?>