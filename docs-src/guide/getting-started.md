# Getting Started

## Requirements

- PHP **8.5** or higher
- [Composer](https://getcomposer.org/)
- A Sharkord account with bot credentials (`identity` + `password`)

## Installation

```bash
composer require buzzmoody/sharkordphp
```

## Environment Setup

SharkordPHP reads credentials from your environment. Create a `.env` file in your
project root (and add it to `.gitignore`):

```ini
HOST=your-sharkord-host
IDENTITY=your-bot-username
PASSWORD=your-bot-password
```

Install `vlucas/phpdotenv` to load it automatically:

```bash
composer require vlucas/phpdotenv
```

## Your First Bot

```php
<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$bot = new Sharkord\Sharkord([
    'host'     => $_ENV['HOST'],
    'identity' => $_ENV['IDENTITY'],
    'password' => $_ENV['PASSWORD'],
]);

$bot->on('ready', function () use ($bot): void {
    echo "Logged in as {$bot->bot->username}\n";
});

$bot->on('message', function (Sharkord\Models\Message $message): void {
    if ($message->content === '!ping') {
        $message->channel->sendMessage('Pong!');
    }
});

$bot->connect();
```

Run it with:

```bash
php bot.php
```

## Constructor Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `host` | `string` | — | Sharkord server hostname (**required**) |
| `identity` | `string` | — | Bot username (**required**) |
| `password` | `string` | — | Bot password (**required**) |
| `reconnect` | `bool` | `true` | Auto-reconnect on disconnect |
| `maxReconnectAttempts` | `int` | `5` | Max reconnect attempts before exit |
| `logLevel` | `string` | `'Notice'` | Minimum Monolog log level |
| `loop` | `LoopInterface\|null` | `null` | Custom ReactPHP event loop |
| `logger` | `LoggerInterface\|null` | `null` | Custom PSR-3 logger |

## Events

Listen for events using `$bot->on(string $event, callable $listener)`.

| Event | Payload | Description |
|-------|---------|-------------|
| `ready` | _(none)_ | Bot authenticated and fully connected |
| `message` | `Message` | A new message was received |
| `message_update` | `Message` | An existing message was edited |
| `message_delete` | `array` | A message was deleted |
| `member_join` | `User` | A user joined the server |
| `member_leave` | `User` | A user left the server |
| `disconnect` | _(none)_ | WebSocket connection closed |

## Custom Logger

Pass any PSR-3 compatible logger to disable the default Monolog output:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('mybot');
$logger->pushHandler(new StreamHandler('bot.log', Logger::DEBUG));

$bot = new Sharkord\Sharkord(
    config: [...],
    logger: $logger,
);
```
