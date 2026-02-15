<?php

	error_reporting(E_ALL);

	require __DIR__ . '/vendor/autoload.php';

	use Sharkord\Sharkord;
	use Sharkord\Models\Message;

	$bot = new Sharkord([
		'identity' 	=> $_ENV['CHAT_USERNAME'],
		'password'	=> $_ENV['CHAT_PASSWORD'],
		'host'		=> $_ENV['CHAT_HOST'],
	]);

	$bot->on('ready', function() use ($bot) {
		echo "Logged in and ready to chat!\n";
	});

	$bot->on('message', function(Message $message) use ($bot) {
		
		echo sprintf(
			"(%s) [#%s] %s: %s\n",
			date("d/m h:i:sA"),
			$message->channel->name,
			$message->user->name,
			$message->content
		);
		
		if ($message->content === '!ping') {
			$message->reply("Pong!");
		}
		
	});

	$bot->run();

?>

