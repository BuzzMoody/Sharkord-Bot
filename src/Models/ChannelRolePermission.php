<?php

	declare(strict_types=1);

	namespace Sharkord\Models;

	use Sharkord\ChannelPermissionFlag;

	/**
	 * Class ChannelRolePermission
	 *
	 * Represents a single role-level permission entry for a channel, as returned
	 * by the channels.getPermissions RPC. Each entry corresponds to one
	 * ChannelPermissionFlag for one role, and carries an explicit allow/deny flag.
	 *
	 * @package Sharkord\Models
	 *
	 * @example
	 * ```php
	 * $channel->getPermissions()->then(function(array $permissions) {
	 *     foreach ($permissions['rolePermissions'] as $entry) {
	 *         $state = $entry->allow ? 'allowed' : 'denied';
	 *         echo "Role {$entry->roleId} — {$entry->permission->value}: {$state}\n";
	 *     }
	 * });
	 * ```
	 */
	class ChannelRolePermission {

		/**
		 * ChannelRolePermission constructor.
		 *
		 * @param int                  $channelId  The ID of the channel this entry belongs to.
		 * @param int                  $roleId     The ID of the role this entry belongs to.
		 * @param ChannelPermissionFlag $permission The permission flag this entry covers.
		 * @param bool                 $allow      Whether the permission is granted (true) or denied (false).
		 * @param int                  $createdAt  Unix timestamp (ms) when this entry was created.
		 * @param int|null             $updatedAt  Unix timestamp (ms) when this entry was last updated, or null.
		 */
		public function __construct(
			public readonly int $channelId,
			public readonly int $roleId,
			public readonly ChannelPermissionFlag $permission,
			public readonly bool $allow,
			public readonly int $createdAt,
			public readonly ?int $updatedAt,
		) {}

		/**
		 * Factory method to create a ChannelRolePermission from a raw API data array.
		 *
		 * @param array $raw The raw permission entry from the server.
		 * @return self
		 */
		public static function fromArray(array $raw): self {

			return new self(
				channelId:  (int) $raw['channelId'],
				roleId:     (int) $raw['roleId'],
				permission: ChannelPermissionFlag::from($raw['permission']),
				allow:      (bool) $raw['allow'],
				createdAt:  (int) $raw['createdAt'],
				updatedAt:  isset($raw['updatedAt']) ? (int) $raw['updatedAt'] : null,
			);

		}

		/**
		 * Returns the permission entry as a plain array. Useful for debugging.
		 *
		 * @return array
		 */
		public function toArray(): array {

			return [
				'channelId'  => $this->channelId,
				'roleId'     => $this->roleId,
				'permission' => $this->permission->value,
				'allow'      => $this->allow,
				'createdAt'  => $this->createdAt,
				'updatedAt'  => $this->updatedAt,
			];

		}

	}

?>