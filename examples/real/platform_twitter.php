<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

/**
 * Twitter/X — Real API Example
 *
 * Posts a real tweet via the Twitter API v2.
 *
 * Required env vars:
 *   TWITTER_CONSUMER_KEY          — API Key
 *   TWITTER_CONSUMER_SECRET       — API Key Secret
 *   TWITTER_ACCESS_TOKEN          — Access Token
 *   TWITTER_ACCESS_TOKEN_SECRET   — Access Token Secret
 *
 * Usage:
 *   export TWITTER_CONSUMER_KEY=...
 *   export TWITTER_CONSUMER_SECRET=...
 *   export TWITTER_ACCESS_TOKEN=...
 *   export TWITTER_ACCESS_TOKEN_SECRET=...
 *   php examples/real/platform_twitter.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Formatting\CharacterTruncator;
use Fopost\Social\Formatting\HashtagExtractor;
use Fopost\Social\Http\HttpClient;
use Fopost\Social\Platforms\Twitter\TwitterFormatter;
use Fopost\Social\Platforms\Twitter\TwitterPlatform;

// -- Load credentials ---------------------------------------------------------

$consumerKey       = requireEnv('TWITTER_CONSUMER_KEY');
$consumerSecret    = requireEnv('TWITTER_CONSUMER_SECRET');
$accessToken       = requireEnv('TWITTER_ACCESS_TOKEN');
$accessTokenSecret = requireEnv('TWITTER_ACCESS_TOKEN_SECRET');

echo "=== Twitter/X — Real API Example ===\n\n";

// -- Set up platform ----------------------------------------------------------

$credentials = new PlatformCredentials('twitter', [
    'consumer_key'        => $consumerKey,
    'consumer_secret'     => $consumerSecret,
    'access_token'        => $accessToken,
    'access_token_secret' => $accessTokenSecret,
]);

$httpClient = new HttpClient();
$formatter  = new TwitterFormatter(new HashtagExtractor(), new CharacterTruncator());
$twitter    = new TwitterPlatform($credentials, $httpClient, $formatter);

// -- Validate credentials -----------------------------------------------------

echo "  Validating credentials...\n";
$valid = $twitter->validateCredentials();
echo "  Credentials valid: " . ($valid ? 'yes' : 'no') . "\n\n";

if (! $valid) {
    echo "  ✗ Cannot proceed with invalid credentials.\n";
    exit(1);
}

// -- Publish a test post ------------------------------------------------------

echo "  Publishing test tweet...\n";

$post = new Post(
    title: 'Hello from FoPost!',
    body: 'Testing the FoPost Social Core library — publishing to Twitter/X via API v2. 🦉',
    url: 'https://fopost.com',
    tags: ['fopost', 'php'],
);

$result = $twitter->publish($post);
printResult($result);

// -- Show constraints ---------------------------------------------------------

echo "\n  Platform constraints:\n";
printConstraints($twitter->constraints());

echo "\nDone.\n";
