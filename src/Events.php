<?php

	/**
	 * @package    SharkordPHP
	 * @author     Buzz Moody
	 * @copyright  Copyright (c) 2026 Buzz Moody
	 * @license    https://opensource.org/licenses/MIT  MIT License
	 * @link       https://github.com/BuzzMoody/SharkordPHP
	 */

	declare(strict_types=1);

	namespace Sharkord;

	/**
	 * Class Events
	 *
	 * Centralised registry of every event name emitted by the SharkordPHP framework.
	 *
	 * Use these constants anywhere you register a listener via {@see \Sharkord\Sharkord::on()}
	 * to benefit from IDE autocompletion, static analysis, and a single source of truth
	 * for event name strings.
	 *
	 * Each constant's value is the exact string passed to {@see \Evenement\EventEmitter::emit()}
	 * internally, so the two styles are always interchangeable:
	 *
	 * ```php
	 * $sharkord->on('message', $handler);
	 * $sharkord->on(Events::MESSAGE_CREATE, $handler);
	 * ```
	 *
	 * @package Sharkord
	 */
	final class Events {

		// -------------------------------------------------------------------------
		// Lifecycle
		// -------------------------------------------------------------------------

		/**
		 * Fired once the bot has fully connected, hydrated its caches, and is ready
		 * to receive and dispatch events.
		 *
		 * Callback signature: `function(\Sharkord\Models\User $bot): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::READY, function(\Sharkord\Models\User $bot): void {
		 *     echo "Logged in as {$bot->name}!";
		 * });
		 * ```
		 */
		public const string READY = 'ready';

		// -------------------------------------------------------------------------
		// Messages
		// -------------------------------------------------------------------------

		/**
		 * Fired when a new message is posted to any channel.
		 *
		 * Callback signature: `function(\Sharkord\Models\Message $message): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::MESSAGE_CREATE, function(\Sharkord\Models\Message $message): void {
		 *     echo "{$message->author->name}: {$message->content}";
		 * });
		 * ```
		 */
		public const string MESSAGE_CREATE = 'message';

		/**
		 * Fired when an existing message is edited.
		 *
		 * Callback signature: `function(\Sharkord\Models\Message $message): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::MESSAGE_UPDATE, function(\Sharkord\Models\Message $message): void {
		 *     echo "Message {$message->id} was updated.";
		 * });
		 * ```
		 */
		public const string MESSAGE_UPDATE = 'messageupdate';

		/**
		 * Fired when a message is deleted.
		 *
		 * Because the message no longer exists on the server, the full model cannot
		 * be reconstructed. The raw payload array is passed directly instead.
		 * The array is guaranteed to contain a scalar `id` key; `channelId` is
		 * present where the server includes it but must be treated as optional.
		 *
		 * Callback signature: `function(array $data): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::MESSAGE_DELETE, function(array $data): void {
		 *     $messageId = $data['id'];
		 *     $channelId = $data['channelId'] ?? null;
		 *     echo "Message {$messageId} was deleted"
		 *         . ($channelId ? " from channel {$channelId}" : '') . '.';
		 * });
		 * ```
		 */
		public const string MESSAGE_DELETE = 'messagedelete';

		/**
		 * Fired when the reaction set on a message changes.
		 *
		 * Only emitted when a `messageupdate` payload contains a `reactions` key
		 * and the reaction data has actually changed since the previous state.
		 *
		 * Callback signature:
		 * `function(\Sharkord\Models\Message $message, array $reactions): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::MESSAGE_REACTION, function(
		 *     \Sharkord\Models\Message $message,
		 *     array $reactions,
		 * ): void {
		 *     echo "Message {$message->id} reactions updated.";
		 * });
		 * ```
		 */
		public const string MESSAGE_REACTION = 'messagereaction';

		/**
		 * Fired when a user begins typing in a channel.
		 *
		 * Both the `User` and `Channel` must be resolvable from cache; if either
		 * is missing the event is silently dropped.
		 *
		 * Callback signature:
		 * `function(\Sharkord\Models\User $user, \Sharkord\Models\Channel $channel): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::MESSAGE_TYPING, function(
		 *     \Sharkord\Models\User    $user,
		 *     \Sharkord\Models\Channel $channel,
		 * ): void {
		 *     echo "{$user->name} is typing in #{$channel->name}...";
		 * });
		 * ```
		 */
		public const string MESSAGE_TYPING = 'messagetyping';

		// -------------------------------------------------------------------------
		// Channels
		// -------------------------------------------------------------------------

		/**
		 * Fired when a new channel is created.
		 *
		 * Callback signature: `function(\Sharkord\Models\Channel $channel): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::CHANNEL_CREATE, function(\Sharkord\Models\Channel $channel): void {
		 *     echo "Channel #{$channel->name} was created.";
		 * });
		 * ```
		 */
		public const string CHANNEL_CREATE = 'channelcreate';

		/**
		 * Fired when an existing channel is updated.
		 *
		 * Callback signature: `function(\Sharkord\Models\Channel $channel): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::CHANNEL_UPDATE, function(\Sharkord\Models\Channel $channel): void {
		 *     echo "Channel #{$channel->name} was updated.";
		 * });
		 * ```
		 */
		public const string CHANNEL_UPDATE = 'channelupdate';

		/**
		 * Fired just before a channel is removed from the cache.
		 *
		 * The `Channel` model is still fully populated at the time the event fires.
		 *
		 * Callback signature: `function(\Sharkord\Models\Channel $channel): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::CHANNEL_DELETE, function(\Sharkord\Models\Channel $channel): void {
		 *     echo "Channel #{$channel->name} was deleted.";
		 * });
		 * ```
		 */
		public const string CHANNEL_DELETE = 'channeldelete';

		// -------------------------------------------------------------------------
		// Users
		// -------------------------------------------------------------------------

		/**
		 * Fired when a new user account is created on the server.
		 *
		 * Callback signature: `function(\Sharkord\Models\User $user): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::USER_CREATE, function(\Sharkord\Models\User $user): void {
		 *     echo "New user registered: {$user->name}.";
		 * });
		 * ```
		 */
		public const string USER_CREATE = 'usercreate';

		/**
		 * Fired when a user comes online (connects to the server).
		 *
		 * The user's status will already be set to `'online'` when the callback fires.
		 *
		 * Callback signature: `function(\Sharkord\Models\User $user): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::USER_JOIN, function(\Sharkord\Models\User $user): void {
		 *     echo "{$user->name} just came online.";
		 * });
		 * ```
		 */
		public const string USER_JOIN = 'userjoin';

		/**
		 * Fired when a user goes offline (disconnects from the server).
		 *
		 * The user's status will already be set to `'offline'` when the callback fires.
		 *
		 * Callback signature: `function(\Sharkord\Models\User $user): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::USER_LEAVE, function(\Sharkord\Models\User $user): void {
		 *     echo "{$user->name} went offline.";
		 * });
		 * ```
		 */
		public const string USER_LEAVE = 'userleave';

		/**
		 * Fired just before a user is permanently removed from the cache.
		 *
		 * The `User` model is still fully populated at the time the event fires.
		 *
		 * Callback signature: `function(\Sharkord\Models\User $user): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::USER_DELETE, function(\Sharkord\Models\User $user): void {
		 *     echo "User {$user->name} was deleted from the server.";
		 * });
		 * ```
		 */
		public const string USER_DELETE = 'userdelete';

		/**
		 * Fired when a user changes their display name.
		 *
		 * The `User` model reflects the **new** name at the time the callback fires.
		 *
		 * Callback signature: `function(\Sharkord\Models\User $user): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::USER_NAME_CHANGE, function(\Sharkord\Models\User $user): void {
		 *     echo "A user changed their name to {$user->name}.";
		 * });
		 * ```
		 */
		public const string USER_NAME_CHANGE = 'namechange';

		/**
		 * Fired when a user is banned from the server.
		 *
		 * Callback signature: `function(\Sharkord\Models\User $user): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::USER_BAN, function(\Sharkord\Models\User $user): void {
		 *     echo "{$user->name} has been banned.";
		 * });
		 * ```
		 */
		public const string USER_BAN = 'ban';

		/**
		 * Fired when a user's ban is lifted.
		 *
		 * Callback signature: `function(\Sharkord\Models\User $user): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::USER_UNBAN, function(\Sharkord\Models\User $user): void {
		 *     echo "{$user->name} has been unbanned.";
		 * });
		 * ```
		 */
		public const string USER_UNBAN = 'unban';

		// -------------------------------------------------------------------------
		// Roles
		// -------------------------------------------------------------------------

		/**
		 * Fired when a new role is created.
		 *
		 * Callback signature: `function(\Sharkord\Models\Role $role): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::ROLE_CREATE, function(\Sharkord\Models\Role $role): void {
		 *     echo "Role '{$role->name}' was created.";
		 * });
		 * ```
		 */
		public const string ROLE_CREATE = 'rolecreate';

		/**
		 * Fired when an existing role is updated.
		 *
		 * Callback signature: `function(\Sharkord\Models\Role $role): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::ROLE_UPDATE, function(\Sharkord\Models\Role $role): void {
		 *     echo "Role '{$role->name}' was updated.";
		 * });
		 * ```
		 */
		public const string ROLE_UPDATE = 'roleupdate';

		/**
		 * Fired just before a role is removed from the cache.
		 *
		 * The `Role` model is still fully populated at the time the event fires.
		 *
		 * Callback signature: `function(\Sharkord\Models\Role $role): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::ROLE_DELETE, function(\Sharkord\Models\Role $role): void {
		 *     echo "Role '{$role->name}' was deleted.";
		 * });
		 * ```
		 */
		public const string ROLE_DELETE = 'roledelete';

		// -------------------------------------------------------------------------
		// Categories
		// -------------------------------------------------------------------------

		/**
		 * Fired when a new category is created.
		 *
		 * Callback signature: `function(\Sharkord\Models\Category $category): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::CATEGORY_CREATE, function(\Sharkord\Models\Category $category): void {
		 *     echo "Category '{$category->name}' was created.";
		 * });
		 * ```
		 */
		public const string CATEGORY_CREATE = 'categorycreate';

		/**
		 * Fired when an existing category is updated.
		 *
		 * Callback signature: `function(\Sharkord\Models\Category $category): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::CATEGORY_UPDATE, function(\Sharkord\Models\Category $category): void {
		 *     echo "Category '{$category->name}' was updated.";
		 * });
		 * ```
		 */
		public const string CATEGORY_UPDATE = 'categoryupdate';

		/**
		 * Fired just before a category is removed from the cache.
		 *
		 * The `Category` model is still fully populated at the time the event fires.
		 *
		 * Callback signature: `function(\Sharkord\Models\Category $category): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::CATEGORY_DELETE, function(\Sharkord\Models\Category $category): void {
		 *     echo "Category '{$category->name}' was deleted.";
		 * });
		 * ```
		 */
		public const string CATEGORY_DELETE = 'categorydelete';

		// -------------------------------------------------------------------------
		// Server
		// -------------------------------------------------------------------------

		/**
		 * Fired when the server's public settings are updated (e.g. name, description).
		 *
		 * Callback signature: `function(\Sharkord\Models\Server $server): void`
		 *
		 * @example
		 * ```php
		 * $sharkord->on(Events::SERVER_UPDATE, function(\Sharkord\Models\Server $server): void {
		 *     echo "Server settings updated. Name is now: {$server->name}";
		 * });
		 * ```
		 */
		public const string SERVER_UPDATE = 'serverupdate';

		/**
		 * Private constructor — this class is a pure constants container and
		 * must never be instantiated.
		 */
		private function __construct() {}

	}

?>