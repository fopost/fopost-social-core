<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

declare(strict_types=1);

/**
 * Example 08: Events
 *
 * Shows how the Publisher fires PostPublished / PostFailed events
 * through the EventDispatcherInterface, and how framework packages
 * can implement their own dispatcher.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Owlstack\Core\Content\Post;
use Owlstack\Core\Events\Contracts\EventDispatcherInterface;
use Owlstack\Core\Events\PostFailed;
use Owlstack\Core\Events\PostPublished;
use Owlstack\Core\Platforms\Contracts\PlatformInterface;
use Owlstack\Core\Platforms\Contracts\PlatformResponseInterface;
use Owlstack\Core\Platforms\PlatformRegistry;
use Owlstack\Core\Platforms\PlatformResponse;
use Owlstack\Core\Publishing\Publisher;
use Owlstack\Core\Publishing\PublishResult;

echo "=== Example 08: Events ===\n\n";

// ── 1. A simple in-memory event dispatcher ──────────────────────────────
$dispatcher = new class implements EventDispatcherInterface {
    /** @var object[] */
    public array $events = [];

    public function dispatch(object $event): void
    {
        $this->events[] = $event;
        $class = (new ReflectionClass($event))->getShortName();
        echo "   [EVENT] {$class} dispatched\n";
    }
};

// ── 2. A stub platform that always succeeds ─────────────────────────────
$successPlatform = new class implements PlatformInterface {
    public function name(): string { return 'success-stub'; }
    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        return PlatformResponse::success('ext-123', 'https://example.com/post/123');
    }
    public function delete(string $externalId): bool { return true; }
    public function validateCredentials(): bool { return true; }
    public function constraints(): array
    {
        return ['max_text_length' => 500, 'max_media_count' => 4, 'supported_media_types' => [], 'max_media_size' => 0];
    }
};

// ── 3. A stub platform that returns a failure response ──────────────────
$failPlatform = new class implements PlatformInterface {
    public function name(): string { return 'fail-stub'; }
    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        return PlatformResponse::failure('API quota exceeded');
    }
    public function delete(string $externalId): bool { return false; }
    public function validateCredentials(): bool { return false; }
    public function constraints(): array
    {
        return ['max_text_length' => 500, 'max_media_count' => 0, 'supported_media_types' => [], 'max_media_size' => 0];
    }
};

// ── 4. A stub platform that throws an exception ─────────────────────────
$crashPlatform = new class implements PlatformInterface {
    public function name(): string { return 'crash-stub'; }
    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        throw new \Owlstack\Core\Exceptions\PlatformException('Connection timed out', 'crash-stub', 504);
    }
    public function delete(string $externalId): bool { return false; }
    public function validateCredentials(): bool { return false; }
    public function constraints(): array
    {
        return ['max_text_length' => 100, 'max_media_count' => 0, 'supported_media_types' => [], 'max_media_size' => 0];
    }
};

// ── 5. Register & Publish ───────────────────────────────────────────────
$registry = new PlatformRegistry();
$registry->register($successPlatform);
$registry->register($failPlatform);
$registry->register($crashPlatform);

$publisher = new Publisher($registry, $dispatcher);

$post = new Post(
    title: 'Events Demo',
    body: 'Testing event dispatching in the publish pipeline.',
);

echo "Publishing to 'success-stub'...\n";
$r1 = $publisher->publish($post, 'success-stub');
echo "   Result: " . ($r1->success ? 'success' : 'failed') . "\n\n";

echo "Publishing to 'fail-stub'...\n";
$r2 = $publisher->publish($post, 'fail-stub');
echo "   Result: " . ($r2->success ? 'success' : 'failed') . "\n";
echo "   Error : {$r2->error}\n\n";

echo "Publishing to 'crash-stub'...\n";
$r3 = $publisher->publish($post, 'crash-stub');
echo "   Result: " . ($r3->success ? 'success' : 'failed') . "\n";
echo "   Error : {$r3->error}\n\n";

// ── 6. Inspect collected events ─────────────────────────────────────────
echo "Collected events ({" . count($dispatcher->events) . "}):\n";
foreach ($dispatcher->events as $i => $event) {
    $class = (new ReflectionClass($event))->getShortName();
    echo "   [{$i}] {$class}";

    if ($event instanceof PostPublished) {
        echo " — platform: {$event->result->platformName}, id: {$event->result->externalId}";
    } elseif ($event instanceof PostFailed) {
        echo " — platform: {$event->result->platformName}, error: {$event->result->error}";
    }
    echo "\n";
}

echo "\n=== Done ===\n";
