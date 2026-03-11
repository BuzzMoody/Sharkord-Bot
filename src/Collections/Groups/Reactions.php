<?php

	declare(strict_types=1);

	namespace Sharkord\Collections\Reactions;

	use Sharkord\Sharkord;
	use Sharkord\Models\User;

	/**
	 * Class Group
	 *
	 * Represents all reactions of a single emoji type on a message.
	 *
	 * Instances are returned from the Reactions collection when accessing
	 * reactions by emoji name (e.g. $message->reactions['olive']).
	 *
	 * @package Sharkord\Collections\Reactions
	 *
	 * @example
	 * ```php
	 * $group = $message->reactions['olive'];
	 *
	 * echo $group->emoji;   // "olive"
	 * echo $group->count;   // 2
	 *
	 * foreach ($group->users as $user) {
	 *     echo $user->name;
	 * }
	 *
	 * // Check whether a specific user has reacted
	 * if ($group->hasUser($sharkord->bot->id)) {
	 *     echo "Bot has already reacted with :olive:";
	 * }
	 * ```
	 */
	class Group {

		/**
		 * Group constructor.
		 *
		 * @param Sharkord $sharkord  Reference to the main bot instance.
		 * @param string   $emoji     The emoji shortcode name this group represents (e.g. "olive").
		 * @param array    $reactions The raw reaction entries from the API for this emoji.
		 */
		public function __construct(
			private readonly Sharkord $sharkord,
			public readonly string $emoji,
			private readonly array $reactions
		) {}

		/**
		 * Returns all resolved User objects who placed this reaction.
		 *
		 * Users that cannot be resolved from the cache (e.g. they left the server
		 * before the reaction event arrived) are silently omitted.
		 *
		 * @return User[]
		 */
		public function getUsers(): array {

			$users = [];

			foreach ($this->reactions as $reaction) {
				if (!empty($reaction['userId']) && $user = $this->sharkord->users->get($reaction['userId'])) {
					$users[] = $user;
				}
			}

			return $users;

		}

		/**
		 * Returns the number of users who placed this reaction.
		 *
		 * @return int
		 */
		public function getCount(): int {

			return count($this->reactions);

		}

		/**
		 * Determines whether a specific user has placed this reaction.
		 *
		 * @param int|string $userId The ID of the user to check.
		 * @return bool
		 */
		public function hasUser(int|string $userId): bool {

			$normalizedId = (string) $userId;

			foreach ($this->reactions as $reaction) {
				if ((string) ($reaction['userId'] ?? '') === $normalizedId) {
					return true;
				}
			}

			return false;

		}

		/**
		 * Returns all raw reaction entries for this emoji group. Useful for debugging.
		 *
		 * @return array
		 */
		public function toArray(): array {

			return $this->reactions;

		}

		/**
		 * Magic getter. Provides shorthand property-style access to common values.
		 *
		 * Supported virtual properties:
		 * - $group->users  → User[] (calls getUsers())
		 * - $group->count  → int    (calls getCount())
		 *
		 * @param string $name Property name.
		 * @return mixed
		 */
		public function __get(string $name): mixed {

			return match($name) {
				'users' => $this->getUsers(),
				'count' => $this->getCount(),
				default => null,
			};

		}

		/**
		 * Magic isset check.
		 *
		 * @param string $name Property name.
		 * @return bool
		 */
		public function __isset(string $name): bool {

			return match($name) {
				'users', 'count' => true,
				default          => false,
			};

		}

	}

?>