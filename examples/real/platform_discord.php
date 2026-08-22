<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

/**
 * Discord — Real API Example
 *
 * Sends a real message to a Discord channel via bot token or webhook.
 *
 * Required env vars (bot mode):
 *   DISCORD_BOT_TOKEN   — Bot token from Discord Developer Portal
 *   DISCORD_CHANNEL_ID  — Target channel ID
 *
 * OR (webhook mode):
 *   DISCORD_WEBHOOK_URL — Incoming webhook URL
 *
 * Usage:
 *   export DISCORD_BOT_TOKEN=...
 *   export DISCORD_CHANNEL_ID=...
 *   php examples/real/platform_discord.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Http\HttpClient;
use Fopost\Social\Platforms\Discord\DiscordFormatter;
use Fopost\Social\Platforms\Discord\DiscordPlatform;

// -- Load credentials ---------------------------------------------------------

$webhookUrl = optionalEnv('DISCORD_WEBHOOK_URL');
$botToken   = optionalEnv('DISCORD_BOT_TOKEN');
$channelId  = optionalEnv('DISCORD_CHANNEL_ID');

if ($webhookUrl === '' && ($botToken === '' || $channelId === '')) {
    echo "\n  ✗ Set either DISCORD_WEBHOOK_URL, or both DISCORD_BOT_TOKEN and DISCORD_CHANNEL_ID.\n";
    echo "    See .env.example for details.\n\n";
    exit(1);
}

echo "=== Discord — Real API Example ===\n\n";

// -- Set up platform ----------------------------------------------------------

if ($webhookUrl !== '') {
    $credentialData = ['webhook_url' => $webhookUrl];
    echo "  Mode: Webhook\n\n";
} else {
    $credentialData = ['bot_token' => $botToken, 'channel_id' => $channelId];
    echo "  Mode: Bot Token (channel: {$channelId})\n\n";
}

$credentials = new PlatformCredentials('discord', $credentialData);

$httpClient = new HttpClient();
$formatter  = new DiscordFormatter();
$discord    = new DiscordPlatform($credentials, $httpClient, $formatter);

// -- Validate credentials -----------------------------------------------------

echo "  Validating credentials...\n";
$valid = $discord->validateCredentials();
echo "  Credentials valid: " . ($valid ? 'yes' : 'no') . "\n\n";

if (! $valid) {
    echo "  ✗ Cannot proceed with invalid credentials.\n";
    exit(1);
}

// -- Publish a test post ------------------------------------------------------

echo "  Publishing test message to Discord...\n";

$post = new Post(
    title: 'Hello from FoPost!',
    body: 'This is a real test message sent to Discord via the FoPost Social Core library. 🦉',
    url: 'https://fopost.com',
    tags: ['fopost', 'discord', 'test'],
);

$result = $discord->publish($post);
printResult($result);

// -- Show constraints ---------------------------------------------------------

echo "\n  Platform constraints:\n";
printConstraints($discord->constraints());

echo "\nDone.\n";
