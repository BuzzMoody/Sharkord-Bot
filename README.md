# SharkordPHP

**A ReactPHP Chatbot Framework for Sharkord**

<div align="center">
    <img src="https://sharkordphp.xyz/logo.png" alt="Description" width="500" />
</div>

SharkordPHP is a lightweight, asynchronous, vibe-coded [Sharkord](https://github.com/Sharkord/sharkord) framework built on top of [ReactPHP](https://reactphp.org/). It handles the heavy lifting of WebSocket connections and event management, allowing you to focus on writing commands and logic.

## Documentation

For details documentation, please visit: https://sharkordphp.xyz/

## Features

* **Asynchronous:** Built on ReactPHP's event loop for non-blocking I/O.
* **Simple Command System:** Create commands by simply dropping files into a folder.
* **State Management:** Built-in caching for Users and Channels.
* **Zero Config Autoloading:** No need to mess with `composer.json` to register your commands.

## Requirements

* PHP 8.5 or higher
* Composer

## Installation

Install the SharkordPHP framework (which provides the Sharkord classes used in the examples below) into your project using Composer:

```bash
composer require buzzmoody/sharkordphp
```

## Getting Started

### 1. Create your project structure

You only need a single PHP file to run the bot and a folder for your commands. Your project folder should look like this:

```text
my-bot/
├── vendor/                 <-- Created by Composer
├── Commands/               <-- Create this folder
│   └── Ping.php            <-- Your first command
├── bot.php                 <-- Your entry file
└── composer.json           <-- Created by Composer
```

### 2. Create the Entry File (`bot.php`)

 Create a file named `bot.php` (or `index.php`) and add the following code. This initializes the bot and tells it where to find your commands. For the latest example, see the Getting Started guide in the documentation: https://sharkordphp.xyz/
 
```php
<?php

	declare(strict_types=1);
	
	error_reporting(E_ALL);

	require __DIR__ . '/vendor/autoload.php';

	use Sharkord\Sharkord;
	use Sharkord\Models\Message;
	use Sharkord\Models\User;
	use Sharkord\Events;

	/*
	* Supports pulling environment variables from .env file as well as Docker container.
	* Hardcode your values at your own peril
	* Example uses SHARKORD_IDENTITY, SHARKORD_PASSWORD and SHARKORD_HOST env vars.
	*/
	$sharkord = new Sharkord(
		config: [
			'identity' 	=> $_ENV['SHARKORD_IDENTITY'] ?? 'your-username',
 			'password'	=> $_ENV['SHARKORD_PASSWORD'] ?? 'your-password',
 			'host'		=> $_ENV['SHARKORD_HOST'] ?? 'server.example.com',
		],
		logLevel: 'Notice',
		reconnect: true,
		maxReconnectAttempts: 5	
	);
	
	/*
	* If you want to use dynamically loaded commands as per the examples directory
	* uncomment the below along with the preg_match if statement further down
	*/
	# $sharkord->commands->loadFromDirectory(__DIR__ . '/Commands');

	$sharkord->on(Events::READY, function(User $bot) use ($sharkord) {
 		$sharkord->logger->notice("Logged in as {$bot->name} and ready to chat!");
	});

	$sharkord->on(Events::MESSAGE_CREATE, function(Message $message) use ($sharkord) {
		
		$sharkord->logger->notice(sprintf(
			"[#%s] %s: %s",
			$message->channel->name,
			$message->author->name,
			$message->content
		));
		
		/*
		* Uncomment if you're using dynamically loaded Commands.
		* Make sure to delete the ping/pong if statement.
		*/
		# if (preg_match('/^!([a-zA-Z]{2,})(?:\s+(.*))?$/', $message->content, $matches)) {
		#	 $sharkord->commands->handle($message, $matches);
		# }
		
		if ($message->content == '!ping') $message->reply('Pong!');
		
	});

	$sharkord->run();

?>

```

### 3. Create a Command (`Commands/Ping.php`) if using dynamically loaded commands.

Create a `Commands` folder. Inside, create a file named `Ping.php`.

Sharkord commands do **not** require namespaces, making them easy to write. Just implement the `CommandInterface`. 

```php
<?php

	use Sharkord\Commands\CommandInterface;
	use Sharkord\Models\Message;
	use Sharkord\Sharkord;

	/**
	 * Class Ping
	 *
	 * A simple command to check if the bot is responsive.
	 * Responds with "Pong!" when invoked.
	 *
	 * @package Sharkord\Commands
	 */
	class Ping implements CommandInterface {
		
		private const RESPONSES = [
			"Pong! Right back at ya.",
			"Ping received. Pong!",
			"Got it!",
			"Ping received, initiating pong sequence... Pong!",
			"Did someone say ping? Pong!",
			"You rang? Pong!",
			"Copy that. Pong!",
			"The answer is always... pong."
		];

		/**
		 * @inheritDoc
		 */
		public function getName(): string {
			return 'ping';
		}

		/**
		 * @inheritDoc
		 */
		public function getDescription(): string {
			return 'Responds with Pong!';
		}
		
		/**
		 * @inheritDoc
		 */
		public function getPattern(): string {
			return '/^ping$/';
		}

		/**
		 * @inheritDoc
		 */
		public function handle(Sharkord $sharkord, Message $message, string $args, array $matches): void {
			$message->reply(self::RESPONSES[array_rand(self::RESPONSES)]);
		}

	}

?>
```

### 4. Run the Bot

Open your terminal and run:

```bash
php bot.php
```

## License

MIT
