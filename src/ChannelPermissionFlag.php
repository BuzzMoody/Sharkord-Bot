<?php

	declare(strict_types=1);

	namespace Sharkord;

	/**
	 * Enum ChannelPermissionFlag
	 *
	 * Represents the individual permission flags that can be granted or denied
	 * per-role on a channel via the channels.updatePermissions RPC.
	 *
	 * @package Sharkord
	 */
	enum ChannelPermissionFlag: string {

		case VIEW_CHANNEL  = 'VIEW_CHANNEL';
		case SEND_MESSAGES = 'SEND_MESSAGES';
		case JOIN          = 'JOIN';
		case SPEAK         = 'SPEAK';
		case SHARE_SCREEN  = 'SHARE_SCREEN';
		case WEBCAM        = 'WEBCAM';

	}

?>