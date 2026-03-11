# Examples

## Responding to Messages

```php
$bot->on('message', function (Sharkord\Models\Message $message): void {

    // Respond with plain text
    if ($message->content === '!hello') {
        $message->channel->sendMessage("Hello, {$message->author->username}!");
        return;
    }

    // Echo the message back
    if (str_starts_with($message->content, '!echo ')) {
        $text = substr($message->content, 6);
        $message->channel->sendMessage($text);
    }

});
```

## Registering Commands

The `CommandRouter` lets you define prefix commands cleanly:

```php
$bot->on('ready', function () use ($bot): void {

    $bot->commands->register('!ping', function (Sharkord\Models\Message $message): void {
        $message->channel->sendMessage('Pong!');
    });

    $bot->commands->register('!info', function (Sharkord\Models\Message $message) use ($bot): void {
        $message->channel->sendMessage("Running SharkordPHP. Server has {$bot->users->count()} users.");
    });

});
```

## Sending Direct Messages

```php
$bot->on('message', function (Sharkord\Models\Message $message): void {

    if ($message->content === '!dm') {
        $message->author->sendDm('Hey! This is a private message.');
    }

});
```

Or open the DM channel first to send multiple messages or access the channel object:

```php
$message->author->openDm()->then(function (Sharkord\Models\Channel $channel): void {
    $channel->sendMessage('First message.');
    $channel->sendMessage('Second message.');
});
```

## Checking Permissions

Use the `Guard` to protect privileged commands:

```php
$bot->commands->register('!kick', function (Sharkord\Models\Message $message) use ($bot): void {

    try {
        $bot->guard->requirePermission(Sharkord\Enums\Permission::MANAGE_USERS);
    } catch (Sharkord\Exceptions\MissingPermissionException $e) {
        $message->channel->sendMessage('You do not have permission to use this command.');
        return;
    }

    // ... kick logic

});
```

## Working with Users

```php
$bot->on('member_join', function (Sharkord\Models\User $user) use ($bot): void {

    // Send a welcome message to a specific channel
    $channel = $bot->channels->findByName('general');
    $channel?->sendMessage("Welcome to the server, {$user->username}!");

});
```

Fetch a user by ID:

```php
$user = $bot->users->get($userId);

if ($user !== null) {
    echo $user->username;
}
```

## Working with Channels

```php
// Get all channels
foreach ($bot->channels->all() as $channel) {
    echo $channel->name . "\n";
}

// Find by name
$general = $bot->channels->findByName('general');
$general?->sendMessage('Hello everyone!');
```

## Bringing It All Together

A minimal but fully-functional bot:

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
    echo "Ready as {$bot->bot->username}\n";

    $bot->commands->register('!help', function (Sharkord\Models\Message $message): void {
        $message->channel->sendMessage(
            "Available commands: `!help`, `!ping`, `!dm`"
        );
    });

    $bot->commands->register('!ping', function (Sharkord\Models\Message $message): void {
        $message->channel->sendMessage('Pong!');
    });

    $bot->commands->register('!dm', function (Sharkord\Models\Message $message): void {
        $message->author->sendDm('Hello from the bot!');
        $message->channel->sendMessage('DM sent!');
    });
});

$bot->on('member_join', function (Sharkord\Models\User $user) use ($bot): void {
    $bot->channels->findByName('general')?->sendMessage(
        "Welcome, {$user->username}! Type `!help` to see what I can do."
    );
});

$bot->connect();
```
