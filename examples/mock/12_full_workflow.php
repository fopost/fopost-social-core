<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

declare(strict_types=1);

/**
 * Example 12: Full End-to-End Workflow
 *
 * Ties everything together:
 *   1. Configure platforms
 *   2. Validate configuration
 *   3. Build platform instances
 *   4. Register them
 *   5. Create a Post
 *   6. Publish to all platforms with event tracking
 *   7. Inspect results and delivery statuses
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Fopost\Social\Config\ConfigValidator;
use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Config\FopostConfig;
use Fopost\Social\Content\Media;
use Fopost\Social\Content\MediaCollection;
use Fopost\Social\Content\Post;
use Fopost\Social\Delivery\DeliveryStatus;
use Fopost\Social\Events\Contracts\EventDispatcherInterface;
use Fopost\Social\Events\PostFailed;
use Fopost\Social\Events\PostPublished;
use Fopost\Social\Formatting\CharacterTruncator;
use Fopost\Social\Formatting\HashtagExtractor;
use Fopost\Social\Http\Contracts\HttpClientInterface;
use Fopost\Social\Platforms\Facebook\FacebookFormatter;
use Fopost\Social\Platforms\Facebook\FacebookPlatform;
use Fopost\Social\Platforms\PlatformRegistry;
use Fopost\Social\Platforms\Telegram\TelegramFormatter;
use Fopost\Social\Platforms\Telegram\TelegramPlatform;
use Fopost\Social\Platforms\Twitter\TwitterFormatter;
use Fopost\Social\Platforms\Twitter\TwitterPlatform;
use Fopost\Social\Publishing\Publisher;

echo "╔══════════════════════════════════════════════════╗\n";
echo "║   Owlstack Core — Full End-to-End Workflow       ║\n";
echo "╚══════════════════════════════════════════════════╝\n\n";

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Step 1: Configuration
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo "STEP 1: Configuration\n";

$config = new FopostConfig(
    platforms: [
        'telegram' => [
            'api_token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
            'channel_username' => '@owlstack_news',
        ],
        'twitter' => [
            'consumer_key' => 'ck_demo',
            'consumer_secret' => 'cs_demo',
            'access_token' => 'at_demo',
            'access_token_secret' => 'ats_demo',
        ],
        'facebook' => [
            'app_id' => 'fb_app_123',
            'app_secret' => 'fb_secret',
            'page_access_token' => 'fb_page_token',
            'page_id' => '1234567890',
        ],
    ],
    options: [
        'default_tags' => ['owlstack'],
    ],
);

echo "   Platforms: " . implode(', ', $config->configuredPlatforms()) . "\n\n";

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Step 2: Validate
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo "STEP 2: Validate configuration\n";

$validator = new ConfigValidator();
try {
    $validator->validateConfig($config);
    echo "   All credentials valid!\n\n";
} catch (\Fopost\Social\Exceptions\FopostException $e) {
    echo "   INVALID: {$e->getMessage()}\n\n";
    exit(1);
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Step 3: Build platform instances (with mock HTTP)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo "STEP 3: Build platform instances\n";

$http = new class implements HttpClientInterface {
    public function get(string $url, array $options = []): array
    {
        return ['status' => 200, 'headers' => [], 'body' => '{"ok":true}'];
    }
    public function post(string $url, array $options = []): array
    {
        if (str_contains($url, 'telegram')) {
            return ['status' => 200, 'headers' => [], 'body' => json_encode([
                'ok' => true, 'result' => ['message_id' => rand(1000, 9999)],
            ])];
        }
        if (str_contains($url, 'x.com')) {
            return ['status' => 200, 'headers' => [], 'body' => json_encode([
                'data' => ['id' => (string) rand(1000000, 9999999)],
            ])];
        }
        if (str_contains($url, 'facebook')) {
            return ['status' => 200, 'headers' => [], 'body' => json_encode([
                'id' => 'page_' . rand(10000, 99999),
            ])];
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

$hashtagExtractor = new HashtagExtractor();
$truncator = new CharacterTruncator();

$platforms = [
    new TelegramPlatform(
        $config->credentials('telegram'),
        $http,
        new TelegramFormatter($hashtagExtractor, $truncator),
    ),
    new TwitterPlatform(
        $config->credentials('twitter'),
        $http,
        new TwitterFormatter($hashtagExtractor, $truncator),
    ),
    new FacebookPlatform(
        $config->credentials('facebook'),
        $http,
        new FacebookFormatter($hashtagExtractor, $truncator),
    ),
];

$registry = new PlatformRegistry();
foreach ($platforms as $p) {
    $registry->register($p);
    echo "   Registered: {$p->name()}\n";
}
echo "\n";

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Step 4: Event dispatcher
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo "STEP 4: Set up event tracking\n";

$eventLog = [];
$dispatcher = new class($eventLog) implements EventDispatcherInterface {
    public function __construct(private array &$log) {}
    public function dispatch(object $event): void
    {
        $this->log[] = $event;
    }
};
echo "   In-memory event dispatcher ready.\n\n";

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Step 5: Create a Post
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo "STEP 5: Create Post\n";

$post = new Post(
    title: 'Owlstack Core v1.0 Released!',
    body: 'We are proud to announce Owlstack Core v1.0 — a framework-agnostic PHP library that lets you publish content to multiple social media platforms with a single, unified API.',
    url: 'https://owlstack.com/blog/v1-release',
    excerpt: 'Owlstack Core v1.0 is here! Publish to Telegram, Twitter/X, and Facebook from one codebase.',
    tags: ['owlstack', 'php', 'opensource', 'social-media'],
    metadata: ['campaign' => 'v1-launch'],
);

echo "   Title  : {$post->title}\n";
echo "   URL    : {$post->url}\n";
echo "   Tags   : " . implode(', ', $post->tags) . "\n";
echo "   hasUrl : " . ($post->hasUrl() ? 'yes' : 'no') . "\n";
echo "   Media  : " . ($post->hasMedia() ? 'yes' : 'no') . "\n\n";

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Step 6: Publish
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo "STEP 6: Publish to all platforms\n\n";

$publisher = new Publisher($registry, $dispatcher);
$results = [];
$deliveries = [];

foreach ($registry->names() as $name) {
    $deliveries[$name] = DeliveryStatus::Pending;
    echo "   [{$deliveries[$name]->value}] {$name}\n";
}
echo "\n";

foreach ($registry->names() as $name) {
    $deliveries[$name] = DeliveryStatus::Publishing;
    echo "   [{$deliveries[$name]->value}] {$name}...\n";

    $result = $publisher->publish($post, $name);
    $results[$name] = $result;

    $deliveries[$name] = $result->success
        ? DeliveryStatus::Published
        : DeliveryStatus::Failed;

    $icon = $result->success ? '✅' : '❌';
    echo "   [{$deliveries[$name]->value}] {$icon} {$name}";
    if ($result->success) {
        echo " → ID: {$result->externalId}";
        if ($result->externalUrl) {
            echo " | URL: {$result->externalUrl}";
        }
    } else {
        echo " → Error: {$result->error}";
    }
    echo "\n\n";
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Step 7: Summary
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo "STEP 7: Summary\n\n";

$succeeded = array_filter($results, fn($r) => $r->success);
$failed    = array_filter($results, fn($r) => $r->failed());

echo "   Published : " . count($succeeded) . "/" . count($results) . "\n";
echo "   Failed    : " . count($failed) . "\n";
echo "   Events    : " . count($eventLog) . " dispatched\n\n";

echo "   Delivery statuses:\n";
foreach ($deliveries as $name => $status) {
    $icon = $status === DeliveryStatus::Published ? '✅' : '❌';
    echo "     {$icon} {$name} → {$status->value}\n";
}

echo "\n   Event log:\n";
foreach ($eventLog as $i => $event) {
    $type = (new ReflectionClass($event))->getShortName();
    $platform = $event->result->platformName;
    echo "     [{$i}] {$type} ({$platform})\n";
}

echo "\n╔══════════════════════════════════════════════════╗\n";
echo "║             Workflow complete!                    ║\n";
echo "╚══════════════════════════════════════════════════╝\n";
