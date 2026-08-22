<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

/**
 * Tumblr — Real API Example
 *
 * Creates a real post on a Tumblr blog via the Tumblr API v2.
 *
 * Required env vars:
 *   TUMBLR_ACCESS_TOKEN     — OAuth access token
 *   TUMBLR_BLOG_IDENTIFIER  — Blog name (e.g., myblog.tumblr.com)
 *
 * Usage:
 *   export TUMBLR_ACCESS_TOKEN=...
 *   export TUMBLR_BLOG_IDENTIFIER=myblog.tumblr.com
 *   php examples/real/platform_tumblr.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Http\HttpClient;
use Fopost\Social\Platforms\Tumblr\TumblrPlatform;

// -- Load credentials ---------------------------------------------------------

$accessToken    = requireEnv('TUMBLR_ACCESS_TOKEN');
$blogIdentifier = requireEnv('TUMBLR_BLOG_IDENTIFIER');

echo "=== Tumblr — Real API Example ===\n\n";
echo "  Blog: {$blogIdentifier}\n\n";

// -- Set up platform ----------------------------------------------------------

$credentials = new PlatformCredentials('tumblr', [
    'access_token'    => $accessToken,
    'blog_identifier' => $blogIdentifier,
]);

$httpClient = new HttpClient();
$tumblr     = new TumblrPlatform($credentials, $httpClient);

// -- Validate credentials -----------------------------------------------------

echo "  Validating credentials...\n";
$valid = $tumblr->validateCredentials();
echo "  Credentials valid: " . ($valid ? 'yes' : 'no') . "\n\n";

if (! $valid) {
    echo "  ✗ Cannot proceed with invalid credentials.\n";
    exit(1);
}

// -- Publish a test post ------------------------------------------------------

echo "  Publishing test post to Tumblr...\n";

$post = new Post(
    title: 'Hello from Owlstack!',
    body: 'This is a real test post published to Tumblr via the Owlstack Core library. 🦉',
    url: 'https://owlstack.dev',
    tags: ['owlstack', 'tumblr', 'test'],
);

$result = $tumblr->publish($post, [
    'state' => 'draft', // Publish as draft for safety — change to 'published' when ready
]);

printResult($result);

// -- Show constraints ---------------------------------------------------------

echo "\n  Platform constraints:\n";
printConstraints($tumblr->constraints());

echo "\nDone.\n";
