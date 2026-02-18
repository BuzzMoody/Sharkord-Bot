<?php

	declare(strict_types=1);

	namespace Sharkord\Models;

	/**
	 * Class Role
	 *
	 * Represents a user role (permissions, color, etc).
	 *
	 * @package Sharkord\Models
	 */
	class Role {

		/**
		 * Role constructor.
		 *
		 * @param int    $id          The unique role ID.
		 * @param string $name        The role name.
		 * @param string $color       The hex color code.
		 * @param array  $permissions List of permission strings.
		 * @param bool   $isDefault   Whether this is the default role.
		 * @param int    $position    The sort position.
		 */
		public function __construct(
			public int $id,
			public string $name,
			public string $color,
			public array $permissions = [],
			public bool $isDefault = false,
			public int $position = 0
		) {}

	}
?>

?>