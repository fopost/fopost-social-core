<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

declare(strict_types=1);

/**
 * Example: Discord Platform Integration
 *
 * Demonstrates publishing content to Discord:
 *   1. Bot token + channel publishing with rich embeds
 *   2. Webhook publishing (no auth needed)
 *   3. Plain text messages (no embed)
 *   4. Custom options (username, avatar, TTS)
 *   5. Message deletion
 *
 * Discord supports two publishing methods:
 * - Bot: Requires a bot token and channel ID
 * - Webhook: Only requires a webhook URL (simplest)
 *
 * @see https://discord.com/developers/docs/resources/message
 * @see https://discord.com/developers/docs/resources/webhook
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Formatting\CharacterTruncator;
use Fopost\Social\Formatting\HashtagExtractor;
use Fopost\Social\Http\Contracts\HttpClientInterface;
use Fopost\Social\Platforms\Discord\DiscordFormatter;
use Fopost\Social\Platforms\Discord\DiscordPlatform;

echo "=== Discord Platform Example ===\n\n";

// ── Mock HTTP client ────────────────────────────────────────────────────
$http = new class implements HttpClientInterface {
    public function get(string $url, array $options = []): array
    {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode(['id' => '123456789', 'username' => 'FopostBot']),
        ];
    }
    public function post(string $url, array $options = []): array
    {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'id' => (string) rand(100000000, 999999999),
                'channel_id' => '987654321012345678',
                'guild_id' => '111222333444555666',
                'content' => $options['json']['content'] ?? null,
            ]),
        ];
    }
    public function put(string $url, array $options = []): array
    {
        return ['status' => 200, 'headers' => [], 'body' => '{}'];
    }
    public function delete(string $url, array $options = []): array
    {
        return ['status' => 204, 'headers' => [], 'body' => ''];
    }
};

$formatter = new DiscordFormatter(
    new HashtagExtractor(),
    new CharacterTruncator(),
);

// ── 1. Bot mode: publish with rich embed ────────────────────────────────
echo "1. Bot mode: rich embed message...\n";
$botCredentials = new PlatformCredentials('discord', [
    'bot_token' => 'your-discord-bot-token',
    'channel_id' => '987654321012345678',
]);

$botPlatform = new DiscordPlatform($botCredentials, $http, $formatter);

$post = new Post(
    title: 'New Release: Owlstack v2.0',
    body: 'We just released Owlstack v2.0 with Discord integration! '
        . 'Now you can publish content directly to your Discord channels.',
    url: 'https://owlstack.com/releases/v2',
    tags: ['release', 'owlstack', 'discord'],
);

$result = $botPlatform->publish($post);
echo "   Success: " . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Message ID: " . $result->externalId() . "\n";
echo "   URL: " . ($result->externalUrl() ?? 'N/A') . "\n\n";

// ── 2. Bot mode: plain text (no embed) ─────────────────────────────────
echo "2. Bot mode: plain text message...\n";
$plainPost = new Post(title: 'Quick Update', body: 'Server maintenance at 10 PM UTC.');

$plainResult = $botPlatform->publish($plainPost, ['embed' => false]);
echo "   Success: " . ($plainResult->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Message ID: " . $plainResult->externalId() . "\n\n";

// ── 3. Webhook mode: publish ────────────────────────────────────────────
echo "3. Webhook mode: rich embed message...\n";
$webhookCredentials = new PlatformCredentials('discord', [
    'webhook_url' => 'https://discord.com/api/webhooks/123456/abc-webhook-token',
]);

$webhookPlatform = new DiscordPlatform($webhookCredentials, $http, $formatter);

$webhookPost = new Post(
    title: 'Automated Alert',
    body: 'New blog post published: "Getting Started with Owlstack"',
    url: 'https://owlstack.com/blog/getting-started',
);

$webhookResult = $webhookPlatform->publish($webhookPost, [
    'username' => 'Owlstack Bot',
    'avatar_url' => 'https://owlstack.com/logo.png',
]);
echo "   Success: " . ($webhookResult->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Message ID: " . $webhookResult->externalId() . "\n\n";

// ── 4. Validate credentials ────────────────────────────────────────────
echo "4. Validating credentials...\n";
echo "   Bot mode valid: " . ($botPlatform->validateCredentials() ? 'Yes' : 'No') . "\n";
echo "   Webhook mode valid: " . ($webhookPlatform->validateCredentials() ? 'Yes' : 'No') . "\n\n";

// ── 5. Delete a message ────────────────────────────────────────────────
echo "5. Deleting a message...\n";
$deleted = $botPlatform->delete('999888777666555444');
echo "   Deleted: " . ($deleted ? 'Yes' : 'No') . "\n\n";

// ── 6. Platform constraints ────────────────────────────────────────────
echo "6. Platform constraints:\n";
$constraints = $botPlatform->constraints();
echo "   Max message length: " . $constraints['max_text_length'] . " chars\n";
echo "   Max embed count: " . $constraints['max_media_count'] . "\n";
echo "   Max file size: " . ($constraints['max_media_size'] / 1024 / 1024) . " MB\n";
echo "   Supported media: " . implode(', ', $constraints['supported_media_types']) . "\n\n";

echo "=== Done ===\n";
