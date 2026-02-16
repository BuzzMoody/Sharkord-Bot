<?php

	namespace Sharkord\Models;

	class User {
		
		public function __construct(
			public int $id,
			public string $name,
			public string $status,
			public array $roleIds = []
		) {}
		
		public function updateStatus(string $status): void {
			$this->status = $status;
		}
		
		public function updateName(string $name): void {
			$this->name = $name;
		}
		
	}

?>