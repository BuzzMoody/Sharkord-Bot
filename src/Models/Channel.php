<?php

	namespace Sharkord\Models;

	use Sharkord\Sharkord;

	class Channel {
		public function __construct(
			public int $id,
			public string $name,
			public string $type,
			private Sharkord $bot // We store the bot instance here
		) {}

		public function sendMessage(string $text): void {
			$this->bot->sendMessage($text, $this->id);
		}
	}

?>