<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

/**
 * Telegram — Real API Example
 *
 * Sends a real message to a Telegram channel or chat via the Bot API.
 *
 * Required env vars:
 *   TELEGRAM_API_TOKEN   — Bot token from @BotFather
 *   TELEGRAM_CHANNEL     — Channel username (e.g., @mychannel) or chat ID
 *
 * Usage:
 *   export TELEGRAM_API_TOKEN=123456:ABC-DEF...
 *   export TELEGRAM_CHANNEL=@mychannel
 *   php examples/real/platform_telegram.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Formatting\CharacterTruncator;
use Owlstack\Core\Formatting\HashtagExtractor;
use Owlstack\Core\Http\HttpClient;
use Owlstack\Core\Platforms\Telegram\TelegramFormatter;
use Owlstack\Core\Platforms\Telegram\TelegramPlatform;

// -- Load credentials ---------------------------------------------------------

$apiToken = requireEnv('TELEGRAM_API_TOKEN');
$channel  = requireEnv('TELEGRAM_CHANNEL');

echo "=== Telegram — Real API Example ===\n\n";

// -- Set up platform ----------------------------------------------------------

$credentials = new PlatformCredentials('telegram', [
    'api_token'        => $apiToken,
    'channel_username' => $channel,
]);

$httpClient = new HttpClient();
$formatter  = new TelegramFormatter(new HashtagExtractor(), new CharacterTruncator());
$telegram   = new TelegramPlatform($credentials, $httpClient, $formatter);

// -- Validate credentials -----------------------------------------------------

echo "  Validating credentials...\n";
$valid = $telegram->validateCredentials();
echo "  Credentials valid: " . ($valid ? 'yes' : 'no') . "\n\n";

if (! $valid) {
    echo "  ✗ Cannot proceed with invalid credentials.\n";
    exit(1);
}

// -- Publish a test post ------------------------------------------------------

echo "  Publishing test message...\n";

$post = new Post(
    title: 'Hello from Owlstack!',
    body: 'This is a real test message sent via the Owlstack Core library. 🦉',
    url: 'https://owlstack.dev',
    tags: ['owlstack', 'telegram', 'test'],
);

$result = $telegram->publish($post);
printResult($result);

// -- Show constraints ---------------------------------------------------------

echo "\n  Platform constraints:\n";
printConstraints($telegram->constraints());

echo "\nDone.\n";
