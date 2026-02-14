<?php

declare(strict_types=1);

/**
 * Example: Pinterest Platform Integration
 *
 * Demonstrates publishing Pins to Pinterest using the API v5:
 *   1. Image Pin creation (public URL)
 *   2. Video Pin creation (pre-uploaded media ID)
 *   3. Board section targeting
 *   4. Alt text, dominant color, link
 *   5. Pin deletion
 *   6. Credential validation
 *
 * Pinterest uses a REST API with Bearer token authentication.
 * Pins are created via POST /v5/pins with a JSON body.
 *
 * @see https://developers.pinterest.com/docs/api/v5/pins-create
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Http\Contracts\HttpClientInterface;
use Owlstack\Core\Platforms\Pinterest\PinterestFormatter;
use Owlstack\Core\Platforms\Pinterest\PinterestPlatform;

echo "=== Pinterest Platform Example ===\n\n";

// ── Mock HTTP client ────────────────────────────────────────────────────
$http = new class implements HttpClientInterface {
    private int $callCount = 0;

    public function get(string $url, array $options = []): array
    {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'username' => 'example_pinner',
                'account_type' => 'BUSINESS',
            ]),
        ];
    }

    public function post(string $url, array $options = []): array
    {
        $this->callCount++;
        $title = $options['json']['title'] ?? 'Untitled';
        return [
            'status' => 201,
            'headers' => [],
            'body' => json_encode([
                'id' => 'pin_' . $this->callCount,
                'title' => $title,
                'creative_type' => 'REGULAR',
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

// ── Set up credentials ──────────────────────────────────────────────────
$credentials = new PlatformCredentials('pinterest', [
    'access_token' => 'your-pinterest-access-token',
    'board_id' => '12345678901234',
]);

$formatter = new PinterestFormatter();
$platform = new PinterestPlatform($credentials, $http, $formatter);

// ── 1. Validate credentials ────────────────────────────────────────────
echo "1. Validating credentials...\n";
$isValid = $platform->validateCredentials();
echo "   Credentials valid: " . ($isValid ? 'Yes' : 'No') . "\n\n";

// ── 2. Platform info ───────────────────────────────────────────────────
echo "2. Platform info\n";
echo "   Name: " . $platform->name() . "\n";
$constraints = $platform->constraints();
echo "   Max description: {$constraints['max_text_length']} characters\n";
echo "   Max title: {$constraints['max_title_length']} characters\n";
echo "   Max link: {$constraints['max_link_length']} characters\n\n";

// ── 3. Publish an Image Pin ────────────────────────────────────────────
echo "3. Publishing Image Pin...\n";
$post = new Post(
    title: 'Rustic Kitchen Design Ideas',
    body: 'Transform your kitchen with these rustic decor ideas.',
    url: 'https://example.com/kitchen-design',
    tags: ['kitchen', 'rustic', 'homedecor', 'diy'],
);

$response = $platform->publish($post, [
    'image_url' => 'https://example.com/images/kitchen.jpg',
    'alt_text' => 'Rustic kitchen with wooden countertops',
]);

echo "   Success: " . ($response->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Pin ID: " . $response->externalId() . "\n";
echo "   URL: " . $response->externalUrl() . "\n\n";

// ── 4. Publish with board section ──────────────────────────────────────
echo "4. Publishing to board section...\n";
$sectionPost = new Post(
    title: 'Easy Pasta Recipe',
    body: 'A 15-minute pasta recipe for busy weeknights.',
    url: 'https://example.com/pasta',
    tags: ['recipe', 'pasta', 'quickmeals'],
);

$sectionResponse = $platform->publish($sectionPost, [
    'image_url' => 'https://example.com/images/pasta.jpg',
    'board_section_id' => 'section-999',
]);

echo "   Success: " . ($sectionResponse->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Pin ID: " . $sectionResponse->externalId() . "\n\n";

// ── 5. Publish Video Pin ───────────────────────────────────────────────
echo "5. Publishing Video Pin...\n";
$videoPost = new Post(
    title: 'DIY Shelf Tutorial',
    body: 'Step-by-step guide to building a floating shelf.',
    tags: ['diy', 'woodworking', 'tutorial'],
);

$videoResponse = $platform->publish($videoPost, [
    'media_id' => 'uploaded-media-456',
    'cover_image_url' => 'https://example.com/images/shelf-cover.jpg',
]);

echo "   Success: " . ($videoResponse->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Pin ID: " . $videoResponse->externalId() . "\n\n";

// ── 6. Description formatting ──────────────────────────────────────────
echo "6. Description formatting demo\n";
$formattedPost = new Post(
    title: 'A Very Long Title for Testing',
    body: 'This is the pin description.',
    url: 'https://example.com',
    tags: ['test', 'demo'],
);

$description = $formatter->format($formattedPost);
$title = $formatter->formatTitle($formattedPost);
echo "   Title: {$title}\n";
echo "   Description:\n";
foreach (explode("\n", $description) as $line) {
    echo "   " . $line . "\n";
}
echo "   Description length: " . mb_strlen($description) . " / {$formatter->maxLength()}\n\n";

// ── 7. Delete a Pin ────────────────────────────────────────────────────
echo "7. Deleting Pin...\n";
$deleted = $platform->delete('pin_1');
echo "   Deleted: " . ($deleted ? 'Yes' : 'No') . "\n\n";

echo "=== Done ===\n";
