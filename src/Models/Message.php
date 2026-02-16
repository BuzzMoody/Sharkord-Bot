<?php

	namespace Sharkord\Models;

	class Message {
		
		public function __construct(
			public int $id,
			public string $content,
			public User $user,
			public Channel $channel
		) {}
		
		public function reply(string $text): void {
			$this->channel->sendMessage($text);
		}
		
	}

?>