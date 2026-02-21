# Sharkord Bot

**A ReactPHP Chatbot Framework for Sharkord**

Sharkord Bot is a lightweight, asynchronous, heavily vibe-coded [Sharkord](https://github.com/Sharkord/sharkord) bot framework built on top of [ReactPHP](https://reactphp.org/). It handles the heavy lifting of WebSocket connections and event management, allowing you to focus on writing commands and logic.

## Features

* **Asynchronous:** Built on ReactPHP's event loop for non-blocking I/O.
* **Simple Command System:** Create commands by simply dropping files into a folder.
* **State Management:** Built-in caching for Users and Channels.
* **Zero Config Autoloading:** No need to mess with `composer.json` to register your commands.

## Requirements

* PHP 8.5 or higher
* Composer

## Installation

Install Sharkord into your project using Composer:

```bash
composer require buzzmoody/sharkordbot
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

Create a file named `bot.php` (or `index.php`) and add the following code. This initializes the bot and tells it where to find your commands. See `examples/Main.php` for latest example.

```php
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
	# $sharkord->loadCommands(__DIR__ . '/Commands');

	$sharkord->on('ready', function() use ($sharkord) {
		$sharkord->logger->notice("Logged in as {$sharkord->bot->name} and ready to chat!");
	});

	$sharkord->on('message', function(Message $message) use ($sharkord) {
		
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
		#	 $sharkord->handleCommand($message, $matches);
		# }
		
		if ($message->content == '!ping') $message->channel->sendMessage('Pong!');
		
	});

	$sharkord->run();

?>

```

### 3. Create a Command (`Commands/Ping.php`)

Create a `Commands` folder. Inside, create a file named `Ping.php`.

Sharkord commands do **not** require namespaces, making them easy to write. Just implement the `CommandInterface`. See `examples/Commands/Ping.php` for latest example.

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

## Advanced Usage

### Using Namespaces
If you prefer to organize your commands with namespaces (PSR-4 style), you can pass the namespace as a second argument to `loadCommands`.

```php
$bot->loadCommands(__DIR__ . '/src/Commands', 'MyBot\\Commands\\');
```

### Event Listeners
You can hook into bot events directly from your `bot.php` file:

```php
$sharkord->on('ready', function() {
    //returns when the bot is connected to the server and all users, roles, channels and permissions are cached.
});

$sharkord->on('message', function(Message $message) {
    //returns the message sent to the server by any user
});

$sharkord->on('namechange', function(User $user) {
   //returns a user who has changed their name
});

$sharkord->on('ban', function(User $user) {
   //returns a user who has been banned
});

$sharkord->on('unban', function(User $user) {
   //returns a user who has been unbanned
});
```

## License

MIT