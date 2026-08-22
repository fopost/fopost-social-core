<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

declare(strict_types=1);

/**
 * Example 05: Platform Registry
 *
 * The PlatformRegistry holds all available platform implementations.
 * Framework packages register platforms at boot time; the Publisher
 * pulls them out by name when publishing.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Fopost\Social\Content\Post;
use Fopost\Social\Platforms\Contracts\PlatformInterface;
use Fopost\Social\Platforms\Contracts\PlatformResponseInterface;
use Fopost\Social\Platforms\PlatformRegistry;
use Fopost\Social\Platforms\PlatformResponse;

echo "=== Example 05: Platform Registry ===\n\n";

// ── Create a stub platform for demonstration ────────────────────────────
// In real usage you'd use TelegramPlatform, TwitterPlatform, etc.
$stubPlatform = new class implements PlatformInterface {
    public function name(): string { return 'stub'; }
    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        return PlatformResponse::success('stub-id-' . rand(100, 999), 'https://stub.example.com/post');
    }
    public function delete(string $externalId): bool { return true; }
    public function validateCredentials(): bool { return true; }
    public function constraints(): array
    {
        return [
            'max_text_length' => 500,
            'max_media_count' => 4,
            'supported_media_types' => ['image/jpeg', 'image/png'],
            'max_media_size' => 5 * 1024 * 1024,
        ];
    }
};

$anotherStub = new class implements PlatformInterface {
    public function name(): string { return 'another'; }
    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        return PlatformResponse::success('another-' . rand(100, 999));
    }
    public function delete(string $externalId): bool { return true; }
    public function validateCredentials(): bool { return true; }
    public function constraints(): array
    {
        return ['max_text_length' => 1000, 'max_media_count' => 1, 'supported_media_types' => [], 'max_media_size' => 0];
    }
};

// ── Register ────────────────────────────────────────────────────────────
$registry = new PlatformRegistry();

echo "1) Empty registry\n";
echo "   has('stub'): " . ($registry->has('stub') ? 'yes' : 'no') . "\n";
echo "   names()    : [" . implode(', ', $registry->names()) . "]\n\n";

$registry->register($stubPlatform);
$registry->register($anotherStub);

echo "2) After registering two platforms\n";
echo "   names() : [" . implode(', ', $registry->names()) . "]\n";
echo "   has('stub')   : " . ($registry->has('stub') ? 'yes' : 'no') . "\n";
echo "   has('twitter'): " . ($registry->has('twitter') ? 'yes' : 'no') . "\n\n";

// ── Retrieve ────────────────────────────────────────────────────────────
echo "3) Retrieve a platform\n";
$platform = $registry->get('stub');
echo "   name()         : {$platform->name()}\n";
echo "   validateCreds(): " . ($platform->validateCredentials() ? 'valid' : 'invalid') . "\n";
echo "   constraints    : max_text_length = {$platform->constraints()['max_text_length']}\n\n";

// ── Publish through the registry ────────────────────────────────────────
echo "4) Quick publish through registry\n";
$post = new Post(title: 'Registry test', body: 'Published via the registry lookup');
$response = $registry->get('stub')->publish($post);
echo "   Success   : " . ($response->isSuccess() ? 'yes' : 'no') . "\n";
echo "   externalId: {$response->externalId()}\n\n";

// ── Error on missing platform ───────────────────────────────────────────
echo "5) Accessing a non-existent platform\n";
try {
    $registry->get('linkedin');
} catch (\Fopost\Social\Exceptions\FopostException $e) {
    echo "   Caught: {$e->getMessage()}\n";
}

echo "\n=== Done ===\n";
