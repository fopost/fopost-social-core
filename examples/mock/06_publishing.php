<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

declare(strict_types=1);

/**
 * Example 06: Publishing with the Publisher
 *
 * Demonstrates the full publish flow:
 *   1. Set up a mock HTTP client (no real API calls)
 *   2. Build platform instances with their formatters
 *   3. Register them in the PlatformRegistry
 *   4. Use Publisher to publish a Post
 *   5. Inspect the PublishResult
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Formatting\CharacterTruncator;
use Owlstack\Core\Formatting\HashtagExtractor;
use Owlstack\Core\Http\Contracts\HttpClientInterface;
use Owlstack\Core\Platforms\Facebook\FacebookFormatter;
use Owlstack\Core\Platforms\Facebook\FacebookPlatform;
use Owlstack\Core\Platforms\PlatformRegistry;
use Owlstack\Core\Platforms\Telegram\TelegramFormatter;
use Owlstack\Core\Platforms\Telegram\TelegramPlatform;
use Owlstack\Core\Platforms\Twitter\TwitterFormatter;
use Owlstack\Core\Platforms\Twitter\TwitterPlatform;
use Owlstack\Core\Publishing\Publisher;

echo "=== Example 06: Publishing ===\n\n";

// ── Mock HTTP client ────────────────────────────────────────────────────
// Returns canned success responses so we don't hit real APIs.
$http = new class implements HttpClientInterface {
    public function get(string $url, array $options = []): array
    {
        return ['status' => 200, 'headers' => [], 'body' => '{"ok":true}'];
    }
    public function post(string $url, array $options = []): array
    {
        if (str_contains($url, 'api.telegram.org')) {
            return [
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'ok' => true,
                    'result' => ['message_id' => rand(1000, 9999), 'chat' => ['id' => -100123]],
                ]),
            ];
        }
        if (str_contains($url, 'api.x.com')) {
            return [
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'data' => ['id' => (string) rand(1000000, 9999999)],
                ]),
            ];
        }
        if (str_contains($url, 'graph.facebook.com')) {
            return [
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'id' => 'page-id_' . rand(100000, 999999),
                ]),
            ];
        }
        return ['status' => 200, 'headers' => [], 'body' => '{}'];
    }
    public function put(string $url, array $options = []): array
    {
        return ['status' => 200, 'headers' => [], 'body' => '{}'];
    }
    public function delete(string $url, array $options = []): array
    {
        return ['status' => 200, 'headers' => [], 'body' => '{"success":true}'];
    }
};

// ── Shared formatting dependencies ──────────────────────────────────────
$hashtagExtractor = new HashtagExtractor();
$truncator        = new CharacterTruncator();

// ── Build platforms ─────────────────────────────────────────────────────
$telegram = new TelegramPlatform(
    credentials: new PlatformCredentials('telegram', [
        'api_token' => 'fake-token',
        'channel_username' => '@owlstack_demo',
    ]),
    httpClient: $http,
    formatter: new TelegramFormatter($hashtagExtractor, $truncator),
);

$twitter = new TwitterPlatform(
    credentials: new PlatformCredentials('twitter', [
        'consumer_key' => 'ck',
        'consumer_secret' => 'cs',
        'access_token' => 'at',
        'access_token_secret' => 'ats',
    ]),
    httpClient: $http,
    formatter: new TwitterFormatter($hashtagExtractor, $truncator),
);

$facebook = new FacebookPlatform(
    credentials: new PlatformCredentials('facebook', [
        'app_id' => 'app123',
        'app_secret' => 'secret',
        'page_access_token' => 'pat',
        'page_id' => '9876',
    ]),
    httpClient: $http,
    formatter: new FacebookFormatter($hashtagExtractor, $truncator),
);

// ── Register ────────────────────────────────────────────────────────────
$registry = new PlatformRegistry();
$registry->register($telegram);
$registry->register($twitter);
$registry->register($facebook);

echo "Registered: " . implode(', ', $registry->names()) . "\n\n";

// ── Create a Post ───────────────────────────────────────────────────────
$post = new Post(
    title: 'Owlstack Core 1.0 is out!',
    body: 'We are thrilled to announce Owlstack Core v1.0 — a framework-agnostic PHP library for publishing to social media platforms.',
    url: 'https://owlstack.com/blog/v1-release',
    tags: ['owlstack', 'php', 'opensource'],
);

// ── Publish to each platform individually ───────────────────────────────
$publisher = new Publisher($registry);

$platforms = ['telegram', 'twitter', 'facebook'];

foreach ($platforms as $name) {
    $result = $publisher->publish($post, $name);
    $icon   = $result->success ? '✅' : '❌';
    echo "{$icon} {$result->platformName}\n";
    if ($result->success) {
        echo "   External ID : {$result->externalId}\n";
        echo "   External URL: " . ($result->externalUrl ?? 'n/a') . "\n";
    } else {
        echo "   Error: {$result->error}\n";
    }
    echo "   Timestamp: {$result->timestamp->format('Y-m-d H:i:s')}\n\n";
}

// ── PublishResult helpers ───────────────────────────────────────────────
echo "PublishResult inspection:\n";
$result = $publisher->publish($post, 'telegram');
echo "  ->success : " . ($result->success ? 'true' : 'false') . "\n";
echo "  ->failed(): " . ($result->failed() ? 'true' : 'false') . "\n\n";

echo "=== Done ===\n";
