# Sharkord Bot v1.2

A lightweight, event-driven, heavily vibe-coded PHP chatbot built with **ReactPHP** and **Ratchet/Pawl**. This bot connects to a Sharkord server via WebSockets (JSON-RPC), caches server data into structured models, and provides a clean API for handling messages.

---

## 🚀 Features

* **Asynchronous Architecture**: Built on the ReactPHP event loop for non-blocking I/O.
* **Object-Oriented Design**: High-level models for `Message`, `User`, and `Channel`.
* **Smart Methods**: Reply directly to messages using `$message->reply()` or send messages via `$channel->sendMessage()`.
* **Automatic Caching**: Automatically maps and caches all server users and channels upon connection.

---

## 🛠️ Installation

### 1. Requirements
* **PHP 8.5+**
* **Composer**

### 2. Clone and Install
```bash
git clone https://github.com/buzzmoody/sharkord-bot.git
cd sharkord-bot
composer install
```

### 3. Environment Setup
Create a `.env` file in the root directory and add your credentials:
```text
CHAT_USERNAME=your_bot_name
CHAT_PASSWORD=your_password
CHAT_HOST=your.domain.here
```

---

## 💻 Usage

The initial release connects to the server and responds to a simple `!ping` command. See `Main.php` for latest version.

```php
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
	
	$bot->loadCommands(__DIR__ . '/src/Commands');

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
		
		if (preg_match('/^!([a-zA-Z]{2,})(?:\s+(.*))?$/', $message->content, $matches)) {
			$bot->handleCommand($message, $matches);
		}
		
	});

	$bot->run();

?>
```

---

## 📁 Project Structure

* `Main.php`: The entry point of your bot.
* `src/Sharkord.php`: The core engine handling auth, WebSockets, and event emission.
* `src/Commands/`: Contains the CommandInterface as well as all command function files.
    * `CommandInterface.php`: The format of how future commands should be built.
    * `Ping.php`: Simple ping/pong command example.
* `src/Models/`: Contains the data structures:
    * `User.php`: Stores user data (ID, name, status, roles).
    * `Channel.php`: Stores channel data and handles `sendMessage()`.
    * `Message.php`: Represents a chat message and handles `reply()`.

---

## 🔒 Security Note
Never commit your `.env` file or your `vendor/` directory to GitHub. These are ignored by default in the project's `.gitignore`.