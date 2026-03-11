<?php

	declare(strict_types=1);

	namespace Sharkord\Collections;

	use Sharkord\Sharkord;
	use Sharkord\Collections\Groups\ReactionGroup;

	/**
	 * Class Reactions
	 *
	 * An array-accessible, iterable collection of reaction group objects
	 * keyed by emoji shortcode name.
	 *
	 * Built from the raw reactions array on a Message, grouping individual
	 * reactions by their emoji so callers can work with them naturally.
	 *
	 * @implements \ArrayAccess<string, ReactionGroup>
	 * @implements \IteratorAggregate<string, ReactionGroup>
	 *
	 * @package Sharkord\Collections
	 *
	 * @example
	 * ```php
	 * $reactions = $message->reactions;
	 *
	 * // Total number of distinct emoji types
	 * echo count($reactions);
	 *
	 * // Iterate over all emoji groups
	 * foreach ($reactions as $emoji => $group) {
	 *     echo ":{$emoji}: — {$group->count} reaction(s)\n";
	 *     foreach ($group->users as $user) {
	 *         echo "  {$user->name}\n";
	 *     }
	 * }
	 *
	 * // Access a specific emoji group directly
	 * if (isset($reactions['olive'])) {
	 *     $group = $reactions['olive'];
	 *     echo $group->count;
	 *     echo $group->hasUser($sharkord->bot->id);
	 * }
	 *
	 * // Check whether the message has any reactions at all
	 * if ($message->reactions->isEmpty()) {
	 *     echo "No reactions yet.";
	 * }
	 * ```
	 */
	class Reactions implements \ArrayAccess, \Countable, \IteratorAggregate {

		/**
		 * @var array<string, \Sharkord\Collections\Groups\Reactions> Groups keyed by emoji shortcode name.
		 */
		private array $groups = [];

		/**
		 * Reactions constructor.
		 *
		 * @param Sharkord $sharkord     Reference to the main bot instance.
		 * @param array    $rawReactions The raw reactions array from the API payload.
		 */
		public function __construct(
			private readonly Sharkord $sharkord,
			array $rawReactions
		) {
			$this->buildGroups($rawReactions);
		}

		/**
		 * Groups the flat raw reactions array by emoji shortcode.
		 *
		 * @param array $rawReactions The raw reactions array from the API.
		 * @return void
		 */
		private function buildGroups(array $rawReactions): void {

			$byEmoji = [];

			foreach ($rawReactions as $reaction) {
				$emoji = (string) ($reaction['emoji'] ?? '');
				if ($emoji === '') {
					continue;
				}
				$byEmoji[$emoji][] = $reaction;
			}

			foreach ($byEmoji as $emoji => $reactions) {
				$this->groups[$emoji] = new ReactionGroup($this->sharkord, $emoji, $reactions);
			}

		}

		/**
		 * Returns all emoji shortcode names present on the message.
		 *
		 * @return string[]
		 */
		public function emojis(): array {

			return array_keys($this->groups);

		}

		/**
		 * Returns true if the message has no reactions at all.
		 *
		 * @return bool
		 */
		public function isEmpty(): bool {

			return empty($this->groups);

		}

		// --- ArrayAccess ---

		/**
		 * @param string $offset The emoji shortcode name.
		 */
		public function offsetExists(mixed $offset): bool {

			return isset($this->groups[(string) $offset]);

		}

		/**
		 * @param string $offset The emoji shortcode name.
		 * @return ReactionGroup|null
		 */
		public function offsetGet(mixed $offset): ?ReactionGroup {

			return $this->groups[(string) $offset] ?? null;

		}

		/** @throws \LogicException Reaction collections are read-only. */
		public function offsetSet(mixed $offset, mixed $value): void {

			throw new \LogicException('Reactions is read-only.');

		}

		/** @throws \LogicException Reaction collections are read-only. */
		public function offsetUnset(mixed $offset): void {

			throw new \LogicException('Reactions is read-only.');

		}

		// --- Countable ---

		/**
		 * Returns the number of distinct emoji types on the message.
		 *
		 * @return int
		 */
		public function count(): int {

			return count($this->groups);

		}

		// --- IteratorAggregate ---

		/**
		 * @return \ArrayIterator<string, \Sharkord\Collections\Groups\Reactions>
		 */
		public function getIterator(): \ArrayIterator {

			return new \ArrayIterator($this->groups);

		}

	}

?>