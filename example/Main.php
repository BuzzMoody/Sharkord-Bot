<?php

	declare(strict_types=1);
	
	error_reporting(E_ALL);

	require __DIR__ . '/vendor/autoload.php';

	use Sharkord\Sharkord;
	use Sharkord\Models\Message;
	use Sharkord\Models\User;

	/*
	* Supports pulling environment variables from .env file as well as Docker container.
	* Hardcode your values at your own peril
	*/
	$sharkord = new Sharkord(
		config: [
			'identity' 	=> $_ENV['CHAT_USERNAME'],
			'password'	=> $_ENV['CHAT_PASSWORD'],
			'host'		=> $_ENV['CHAT_HOST'],
		],
		logLevel: 'Notice'
	);
	
	/*
	* If you want to use dynamically loaded commands as per the examples directory
	* uncomment the below along with the preg_match if statement further down
	*/
	# $bot->loadCommands(__DIR__ . '/Commands');

	$sharkord->on('ready', function() use ($sharkord) {
		$sharkord->logger->notice("Logged in as {$sharkord->bot->name} and ready to chat!");
	});

	$sharkord->on('message', function(Message $message) use ($sharkord) {
		
		$sharkord->logger->notice(sprintf(
			"[#%s] %s: %s",
			$message->channel->name,
			$message->user->name,
			$message->content
		));
		
		/*
		* Uncomment if you're using dynamically loaded Commands.
		* Make sure to delete the ping/pong if statement.
		*/
		# if (preg_match('/^!([a-zA-Z]{2,})(?:\s+(.*))?$/', $message->content, $matches)) {
		#	 $sharkord->handleCommand($message, $matches);
		# }
		
		if ($message->content == '!ping') $message->channel->sendMessage('Pong!');
		
	});

	$sharkord->run();

?>

