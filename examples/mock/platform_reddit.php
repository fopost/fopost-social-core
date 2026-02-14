<?php

declare(strict_types=1);

/**
 * Example: Reddit Platform Integration
 *
 * Demonstrates publishing content to Reddit:
 *   1. Self-posts (text posts) to a subreddit
 *   2. Link posts (URL submissions) to a subreddit
 *   3. Post options (flair, NSFW, spoiler)
 *   4. Credential validation
 *
 * Reddit API uses OAuth 2.0 bearer tokens and requires a custom
 * User-Agent header. All posts are submitted to a specific subreddit.
 *
 * @see https://www.reddit.com/dev/api/
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Formatting\CharacterTruncator;
use Owlstack\Core\Formatting\HashtagExtractor;
use Owlstack\Core\Http\Contracts\HttpClientInterface;
use Owlstack\Core\Platforms\Reddit\RedditFormatter;
use Owlstack\Core\Platforms\Reddit\RedditPlatform;

echo "=== Reddit Platform Example ===\n\n";

// ── Mock HTTP client ────────────────────────────────────────────────────
$http = new class implements HttpClientInterface {
    public function get(string $url, array $options = []): array
    {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode(['name' => 'testuser', 'id' => 't2_12345']),
        ];
    }
    public function post(string $url, array $options = []): array
    {
        $kind = $options['form_params']['kind'] ?? 'self';
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'json' => [
                    'errors' => [],
                    'data' => [
                        'name' => 't3_' . substr(md5((string) time()), 0, 6),
                        'url' => 'https://www.reddit.com/r/'
                            . ($options['form_params']['sr'] ?? 'test')
                            . '/comments/abc123/',
                    ],
                ],
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

// ── Set up credentials ──────────────────────────────────────────────────
$credentials = new PlatformCredentials('reddit', [
    'client_id' => 'your-reddit-client-id',
    'client_secret' => 'your-reddit-client-secret',
    'access_token' => 'your-reddit-oauth-token',
    'username' => 'your-reddit-username',
    'subreddit' => 'your_default_subreddit',
]);

$formatter = new RedditFormatter(
    new HashtagExtractor(),
    new CharacterTruncator(),
);

$platform = new RedditPlatform($credentials, $http, $formatter);

// ── 1. Validate credentials ────────────────────────────────────────────
echo "1. Validating credentials...\n";
$isValid = $platform->validateCredentials();
echo "   Credentials valid: " . ($isValid ? 'Yes' : 'No') . "\n\n";

// ── 2. Publish a self-post (text) ──────────────────────────────────────
echo "2. Publishing a self-post...\n";
$post = new Post(
    title: 'Just discovered Owlstack for social media automation',
    body: "I've been looking for a way to publish content across multiple platforms "
        . "and found Owlstack. It supports Telegram, Twitter, Facebook, and now Reddit!\n\n"
        . "Has anyone else tried something similar?",
    tags: ['php', 'automation', 'socialmedia'],
);

$result = $platform->publish($post, ['subreddit' => 'php']);
echo "   Success: " . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Post ID: " . $result->externalId() . "\n";
echo "   URL: " . $result->externalUrl() . "\n\n";

// ── 3. Publish a link post ─────────────────────────────────────────────
echo "3. Publishing a link post...\n";
$linkPost = new Post(
    title: 'Owlstack: Open-source social media publishing engine',
    body: '',
    url: 'https://owlstack.com',
);

$linkResult = $platform->publish($linkPost, ['subreddit' => 'opensource']);
echo "   Success: " . ($linkResult->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Post ID: " . $linkResult->externalId() . "\n";
echo "   URL: " . $linkResult->externalUrl() . "\n\n";

// ── 4. Publish with options (flair, NSFW, spoiler) ─────────────────────
echo "4. Publishing with flair and options...\n";
$optionsPost = new Post(
    title: 'Showcase: My new PHP library for content syndication',
    body: 'Built with zero framework dependencies, supports multiple platforms.',
);

$optionsResult = $platform->publish($optionsPost, [
    'subreddit' => 'PHP',
    'flair_text' => 'Showcase',
    'nsfw' => false,
    'spoiler' => false,
    'sendreplies' => true,
]);
echo "   Success: " . ($optionsResult->isSuccess() ? 'Yes' : 'No') . "\n";
echo "   Post ID: " . $optionsResult->externalId() . "\n\n";

// ── 5. Delete a post ───────────────────────────────────────────────────
echo "5. Deleting a post...\n";
$deleted = $platform->delete('t3_abc123');
echo "   Deleted: " . ($deleted ? 'Yes' : 'No') . "\n\n";

// ── 6. Platform constraints ────────────────────────────────────────────
echo "6. Platform constraints:\n";
$constraints = $platform->constraints();
echo "   Max body length: " . $constraints['max_text_length'] . " chars\n";
echo "   Max title length: " . $constraints['max_title_length'] . " chars\n";
echo "   Max media count: " . $constraints['max_media_count'] . "\n";
echo "   Supported media: " . implode(', ', $constraints['supported_media_types']) . "\n\n";

echo "=== Done ===\n";
