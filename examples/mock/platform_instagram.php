<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

declare(strict_types=1);

/**
 * Example: Instagram Platform Integration
 *
 * Demonstrates publishing content to Instagram using the Content Publishing API:
 *   1. Single image posts (public URL required)
 *   2. Reels publishing
 *   3. Stories publishing
 *   4. Carousel (multi-image) posts
 *   5. Credential validation
 *
 * Instagram uses Meta's Graph API with a two-step container flow:
 *   Step 1 → Create a media container (POST /<IG_USER_ID>/media)
 *   Step 2 → Publish the container (POST /<IG_USER_ID>/media_publish)
 *
 * All media (images/videos) must be hosted on a publicly accessible URL.
 *
 * @see https://developers.facebook.com/docs/instagram-platform/content-publishing
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Http\Contracts\HttpClientInterface;
use Fopost\Social\Platforms\Instagram\InstagramFormatter;
use Fopost\Social\Platforms\Instagram\InstagramPlatform;

echo "=== Instagram Platform Example ===\n\n";

// ── Mock HTTP client ────────────────────────────────────────────────────
$http = new class implements HttpClientInterface {
    private int $callCount = 0;

    public function get(string $url, array $options = []): array
    {
        // Simulates GET /<ig_user_id>?fields=id,username
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'id' => '17841400000000',
                'username' => 'example_account',
            ]),
        ];
    }

    public function post(string $url, array $options = []): array
    {
        $this->callCount++;

        // Container publish step → return final media ID
        if (str_contains($url, '/media_publish')) {
            return [
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['id' => 'media_' . $this->callCount]),
            ];
        }

        // Container creation step → return container ID
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode(['id' => 'container_' . $this->callCount]),
        ];
    }

    public function put(string $url, array $options = []): array
    {
        return ['status' => 200, 'headers' => [], 'body' => '{}'];
    }

    public function delete(string $url, array $options = []): array
    {
        return ['status' => 200, 'headers' => [], 'body' => '{}'];
    }
};

// ── Set up credentials ──────────────────────────────────────────────────
$credentials = new PlatformCredentials('instagram', [
    'access_token' => 'your-instagram-access-token',
    'instagram_account_id' => '17841400000000',
]);

$formatter = new InstagramFormatter();
$platform = new InstagramPlatform($credentials, $http, $formatter);

// ── 1. Validate credentials ────────────────────────────────────────────
echo "1. Validating credentials...\n";
$isValid = $platform->validateCredentials();
echo "   Credentials valid: " . ($isValid ? 'Yes' : 'No') . "\n\n";

// ── 2. Platform info ───────────────────────────────────────────────────
echo "2. Platform info\n";
echo "   Name: " . $platform->name() . "\n";
$constraints = $platform->constraints();
echo "   Max caption: {$constraints['max_text_length']} characters\n";
echo "   Max carousel items: {$constraints['max_carousel_items']}\n";
echo "   Rate limit: {$constraints['posts_per_24h']} posts per 24h\n\n";

// ── 3. Publish single image ────────────────────────────────────────────
echo "3. Publishing single image...\n";
$post = new Post(
    title: 'Beautiful Sunset',
    body: 'Captured this incredible view today!',
    url: 'https://example.com/sunset-post',
    tags: ['sunset', 'photography', 'nature'],
);

$response = $platform->publish($post, [
    'image_url' => 'https://example.com/images/sunset.jpg',
    'alt_text' => 'Orange sunset over the ocean',
]);

echo "   Success: " . ($response->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Media ID: " . $response->externalId() . "\n\n";

// ── 4. Publish a Reel ──────────────────────────────────────────────────
echo "4. Publishing a Reel...\n";
$reelPost = new Post(
    title: '',
    body: 'Quick tutorial on PHP hashtag formatting',
    tags: ['php', 'tutorial', 'reels'],
);

$reelResponse = $platform->publish($reelPost, [
    'media_type' => 'REELS',
    'video_url' => 'https://example.com/videos/tutorial.mp4',
    'cover_url' => 'https://example.com/images/cover.jpg',
    'share_to_feed' => true,
]);

echo "   Success: " . ($reelResponse->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Media ID: " . $reelResponse->externalId() . "\n\n";

// ── 5. Publish a Story ─────────────────────────────────────────────────
echo "5. Publishing a Story...\n";
$storyPost = new Post(title: '', body: '');

$storyResponse = $platform->publish($storyPost, [
    'media_type' => 'STORIES',
    'image_url' => 'https://example.com/images/story.jpg',
]);

echo "   Success: " . ($storyResponse->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Media ID: " . $storyResponse->externalId() . "\n\n";

// ── 6. Publish Carousel ────────────────────────────────────────────────
echo "6. Publishing Carousel...\n";
$carouselPost = new Post(
    title: 'Project Gallery',
    body: 'Swipe through our latest work!',
    tags: ['gallery', 'design'],
);

$carouselResponse = $platform->publish($carouselPost, [
    'carousel' => [
        ['image_url' => 'https://example.com/images/slide1.jpg'],
        ['image_url' => 'https://example.com/images/slide2.jpg'],
        ['video_url' => 'https://example.com/videos/slide3.mp4'],
    ],
]);

echo "   Success: " . ($carouselResponse->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Media ID: " . $carouselResponse->externalId() . "\n\n";

// ── 7. Caption formatting ──────────────────────────────────────────────
echo "7. Caption formatting demo\n";
$formattedPost = new Post(
    title: 'Launch Day!',
    body: 'We are thrilled to announce our new product.',
    url: 'https://productlaunch.example.com',
    tags: ['launch', 'startup', 'innovation'],
);

$caption = $formatter->format($formattedPost);
echo "   Formatted caption:\n";
echo "   ---\n";
foreach (explode("\n", $caption) as $line) {
    echo "   " . $line . "\n";
}
echo "   ---\n";
echo "   Caption length: " . mb_strlen($caption) . " / {$formatter->maxLength()}\n\n";

// ── 8. Delete (not supported) ──────────────────────────────────────────
echo "8. Delete attempt...\n";
try {
    $platform->delete('media-id');
} catch (\Fopost\Social\Exceptions\PlatformException $e) {
    echo "   Expected: " . $e->getMessage() . "\n\n";
}

echo "=== Done ===\n";
