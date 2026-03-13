<?php

	declare(strict_types=1);

	namespace Sharkord;

	/**
	 * Enum ChannelType
	 *
	 * Represents the type of a channel on the server.
	 *
	 * @package Sharkord
	 */
	enum ChannelType: string {

		case TEXT  = 'TEXT';
		case VOICE = 'VOICE';

	}

?>