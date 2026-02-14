# Owlstack Core

Framework-agnostic PHP core for [Owlstack](https://owlstack.com) — a content publishing and synchronization engine for social media platforms.

## About

This package contains the shared core logic used by all Owlstack framework integrations (Laravel, WordPress, etc.). It provides:

- **Platform abstractions** — Interfaces and implementations for Telegram, Twitter/X, and Facebook
- **Content model** — Framework-agnostic Post, Media, and MediaCollection value objects
- **Publishing engine** — Orchestrator that formats and publishes content to platforms
- **Formatting pipeline** — Platform-specific formatters with character truncation and hashtag extraction
- **Authentication contracts** — OAuth handler and token store interfaces for framework implementations
- **Event system** — Dispatcher interface and event objects for publish lifecycle hooks
- **HTTP client** — cURL-based HTTP client with zero framework dependencies

## Installation

```bash
composer require owlstack/owlstack-core
```

## Requirements

- PHP 8.1+
- ext-curl
- ext-json

## Usage

```php
use Owlstack\Core\Content\Post;
use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Http\HttpClient;
use Owlstack\Core\Platforms\PlatformRegistry;
use Owlstack\Core\Platforms\Telegram\TelegramPlatform;
use Owlstack\Core\Platforms\Telegram\TelegramFormatter;
use Owlstack\Core\Publishing\Publisher;

// Set up platform
$credentials = new PlatformCredentials('telegram', [
    'api_token' => 'your-bot-token',
    'chat_id' => '@your-channel',
]);

$httpClient = new HttpClient();
$formatter = new TelegramFormatter();
$platform = new TelegramPlatform($credentials, $httpClient, $formatter);

// Register platform
$registry = new PlatformRegistry();
$registry->register($platform);

// Publish
$publisher = new Publisher($registry);
$post = new Post(
    title: 'Hello World',
    body: 'My first post via Owlstack',
    url: 'https://example.com/hello-world',
);

$result = $publisher->publish($post, 'telegram');
```

## Architecture

This package is designed to have **zero framework dependencies**. It defines contracts (interfaces) for infrastructure concerns like storage, queues, and events — framework packages provide the concrete implementations.

## License

MIT License. See [LICENSE](LICENSE) for details.
