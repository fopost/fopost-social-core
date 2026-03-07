<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

/**
 * Reddit — Real API Example
 *
 * Creates a real self-post on a Reddit subreddit.
 *
 * Required env vars:
 *   REDDIT_CLIENT_ID      — Reddit app client ID
 *   REDDIT_CLIENT_SECRET   — Reddit app client secret
 *   REDDIT_ACCESS_TOKEN    — OAuth2 access token
 *   REDDIT_USERNAME        — Reddit username
 *   REDDIT_SUBREDDIT       — Target subreddit (without r/ prefix, e.g., "test")
 *
 * Usage:
 *   export REDDIT_CLIENT_ID=...
 *   export REDDIT_CLIENT_SECRET=...
 *   export REDDIT_ACCESS_TOKEN=...
 *   export REDDIT_USERNAME=...
 *   export REDDIT_SUBREDDIT=test
 *   php examples/real/platform_reddit.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Http\HttpClient;
use Owlstack\Core\Platforms\Reddit\RedditFormatter;
use Owlstack\Core\Platforms\Reddit\RedditPlatform;

// -- Load credentials ---------------------------------------------------------

$clientId     = requireEnv('REDDIT_CLIENT_ID');
$clientSecret = requireEnv('REDDIT_CLIENT_SECRET');
$accessToken  = requireEnv('REDDIT_ACCESS_TOKEN');
$username     = requireEnv('REDDIT_USERNAME');
$subreddit    = requireEnv('REDDIT_SUBREDDIT');

echo "=== Reddit — Real API Example ===\n\n";
echo "  Subreddit: r/{$subreddit}\n\n";

// -- Set up platform ----------------------------------------------------------

$credentials = new PlatformCredentials('reddit', [
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'access_token'  => $accessToken,
    'username'      => $username,
    'subreddit'     => $subreddit,
]);

$httpClient = new HttpClient();
$formatter  = new RedditFormatter();
$reddit     = new RedditPlatform($credentials, $httpClient, $formatter);

// -- Validate credentials -----------------------------------------------------

echo "  Validating credentials...\n";
$valid = $reddit->validateCredentials();
echo "  Credentials valid: " . ($valid ? 'yes' : 'no') . "\n\n";

if (! $valid) {
    echo "  ✗ Cannot proceed with invalid credentials.\n";
    exit(1);
}

// -- Publish a test post ------------------------------------------------------

echo "  Submitting test self-post to r/{$subreddit}...\n";

$post = new Post(
    title: 'Hello from Owlstack!',
    body: 'This is a real test post submitted via the Owlstack Core library. 🦉',
    url: 'https://owlstack.dev',
    tags: ['owlstack', 'reddit', 'test'],
);

$result = $reddit->publish($post, [
    'subreddit' => $subreddit,
    'kind'      => 'self',
]);

printResult($result);

// -- Show constraints ---------------------------------------------------------

echo "\n  Platform constraints:\n";
printConstraints($reddit->constraints());

echo "\nDone.\n";
