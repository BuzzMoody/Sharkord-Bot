<?php

	namespace Sharkord\Models;

	class User {
		public function __construct(
			public int $id,
			public string $name,
			public string $status,
			public array $roleIds = []
		) {}
	}

?>