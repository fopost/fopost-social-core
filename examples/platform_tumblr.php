<?php

/**
 * Tumblr Platform Integration Example
 *
 * Demonstrates how to use the Tumblr API v2 platform with NPF posts:
 *   1. Text posts (heading + body blocks)
 *   2. Image posts (with alt text)
 *   3. Video posts
 *   4. Link posts
 *   5. Audio posts
 *   6. Draft posts
 *   7. Post deletion
 *   8. Credential validation
 *
 * Requires: OAuth 2.0 access token and a blog identifier.
 *
 * @see https://www.tumblr.com/docs/en/api/v2
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Content\Post;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\Tumblr\TumblrFormatter;
use Synglify\Core\Platforms\Tumblr\TumblrPlatform;

// -- Mock HTTP client for demonstration (replace with real HttpClient) --------

$mockHttp = new class implements HttpClientInterface {
    private int $callCount = 0;

    public function get(string $url, array $options = []): array
    {
        echo "  GET {$url}\n";

        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'meta' => ['status' => 200, 'msg' => 'OK'],
                'response' => [
                    'blog' => [
                        'name' => 'testblog',
                        'title' => 'My Test Blog',
                        'posts' => 42,
                        'url' => 'https://testblog.tumblr.com/',
                    ],
                ],
            ]),
        ];
    }

    public function post(string $url, array $options = []): array
    {
        $this->callCount++;
        echo "  POST {$url}\n";

        // Delete endpoint
        if (str_contains($url, '/post/delete')) {
            return [
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'meta' => ['status' => 200, 'msg' => 'OK'],
                    'response' => [],
                ]),
            ];
        }

        // Create post
        return [
            'status' => 201,
            'headers' => [],
            'body' => json_encode([
                'meta' => ['status' => 201, 'msg' => 'Created'],
                'response' => ['id' => '100' . $this->callCount],
            ]),
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

// -- Credentials --------------------------------------------------------------

$credentials = new PlatformCredentials('tumblr', [
    'access_token' => 'your-tumblr-oauth2-token',
    'blog_identifier' => 'testblog.tumblr.com',
]);

$platform = new TumblrPlatform($credentials, $mockHttp);
$formatter = new TumblrFormatter();

// =============================================================================
//  1. Platform Info
// =============================================================================

echo "=== Tumblr Platform ===\n\n";
echo "Platform name : {$platform->name()}\n";
echo "Constraints   :\n";
foreach ($platform->constraints() as $key => $value) {
    $display = is_array($value) ? implode(', ', $value) : $value;
    echo "  {$key}: {$display}\n";
}
echo "\n";

// =============================================================================
//  2. Validate Credentials
// =============================================================================

echo "=== Validate Credentials ===\n\n";
$valid = $platform->validateCredentials();
echo 'Credentials valid: ' . ($valid ? 'Yes' : 'No') . "\n\n";

// =============================================================================
//  3. Formatter
// =============================================================================

echo "=== Formatter ===\n\n";

$post = new Post(
    title: 'Adventures in PHP',
    body: "Today I built a social media library.\n\nIt supports multiple platforms!",
    url: 'https://example.com/blog/php-social',
    tags: ['php', 'social media', 'tumblr'],
);

echo "Platform    : {$formatter->platform()}\n";
echo "Max length  : {$formatter->maxLength()}\n";
echo "Title       : {$formatter->formatTitle($post)}\n";
echo "Tags        : {$formatter->formatTags($post)}\n";
echo "Formatted HTML:\n{$formatter->format($post)}\n\n";

// =============================================================================
//  4. Text Post
// =============================================================================

echo "=== Text Post ===\n\n";

$textPost = new Post(
    title: 'Hello Tumblr',
    body: 'This is my first post via the API.',
    url: 'https://example.com',
    tags: ['hello', 'first post'],
);

$result = $platform->publish($textPost);
echo 'Success  : ' . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Post ID  : {$result->externalId()}\n";
echo "Post URL : {$result->externalUrl()}\n\n";

// =============================================================================
//  5. Image Post
// =============================================================================

echo "=== Image Post ===\n\n";

$imagePost = new Post(
    title: 'Sunset Photo',
    body: 'Captured this amazing sunset yesterday.',
    tags: ['photography', 'sunset'],
);

$result = $platform->publish($imagePost, [
    'post_type' => 'image',
    'image_url' => 'https://example.com/photos/sunset.jpg',
    'alt_text' => 'A beautiful sunset over the ocean',
]);
echo 'Success  : ' . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Post ID  : {$result->externalId()}\n\n";

// =============================================================================
//  6. Video Post
// =============================================================================

echo "=== Video Post ===\n\n";

$videoPost = new Post(
    title: 'Tutorial Video',
    body: 'Learn how to build APIs in 10 minutes.',
    tags: ['video', 'tutorial'],
);

$result = $platform->publish($videoPost, [
    'post_type' => 'video',
    'video_url' => 'https://example.com/videos/tutorial.mp4',
]);
echo 'Success  : ' . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Post ID  : {$result->externalId()}\n\n";

// =============================================================================
//  7. Link Post
// =============================================================================

echo "=== Link Post ===\n\n";

$linkPost = new Post(
    title: 'Interesting Article',
    body: 'This article on microservices is worth reading.',
    tags: ['link', 'microservices'],
);

$result = $platform->publish($linkPost, [
    'post_type' => 'link',
    'link_url' => 'https://example.com/articles/microservices',
]);
echo 'Success  : ' . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Post ID  : {$result->externalId()}\n\n";

// =============================================================================
//  8. Draft Post
// =============================================================================

echo "=== Draft Post ===\n\n";

$draftPost = new Post(
    title: 'Work in Progress',
    body: 'This post is saved as a draft for later editing.',
);

$result = $platform->publish($draftPost, ['state' => 'draft']);
echo 'Success  : ' . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Post ID  : {$result->externalId()}\n\n";

// =============================================================================
//  9. Delete Post
// =============================================================================

echo "=== Delete Post ===\n\n";

$deleted = $platform->delete('1001');
echo 'Deleted  : ' . ($deleted ? 'Yes' : 'No') . "\n\n";

echo "Done!\n";
