<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

/**
 * Facebook — Real API Example
 *
 * Posts a real message to a Facebook Page via the Graph API.
 *
 * Required env vars:
 *   FACEBOOK_APP_ID             — Facebook App ID
 *   FACEBOOK_APP_SECRET         — Facebook App Secret
 *   FACEBOOK_PAGE_ACCESS_TOKEN  — Page Access Token (with pages_manage_posts)
 *   FACEBOOK_PAGE_ID            — Facebook Page ID
 *
 * Usage:
 *   export FACEBOOK_APP_ID=...
 *   export FACEBOOK_APP_SECRET=...
 *   export FACEBOOK_PAGE_ACCESS_TOKEN=...
 *   export FACEBOOK_PAGE_ID=...
 *   php examples/real/platform_facebook.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Formatting\CharacterTruncator;
use Fopost\Social\Formatting\HashtagExtractor;
use Fopost\Social\Http\HttpClient;
use Fopost\Social\Platforms\Facebook\FacebookFormatter;
use Fopost\Social\Platforms\Facebook\FacebookPlatform;

// -- Load credentials ---------------------------------------------------------

$appId           = requireEnv('FACEBOOK_APP_ID');
$appSecret       = requireEnv('FACEBOOK_APP_SECRET');
$pageAccessToken = requireEnv('FACEBOOK_PAGE_ACCESS_TOKEN');
$pageId          = requireEnv('FACEBOOK_PAGE_ID');

echo "=== Facebook — Real API Example ===\n\n";

// -- Set up platform ----------------------------------------------------------

$credentials = new PlatformCredentials('facebook', [
    'app_id'            => $appId,
    'app_secret'        => $appSecret,
    'page_access_token' => $pageAccessToken,
    'page_id'           => $pageId,
]);

$httpClient = new HttpClient();
$formatter  = new FacebookFormatter(new HashtagExtractor(), new CharacterTruncator());
$facebook   = new FacebookPlatform($credentials, $httpClient, $formatter);

// -- Validate credentials -----------------------------------------------------

echo "  Validating credentials...\n";
$valid = $facebook->validateCredentials();
echo "  Credentials valid: " . ($valid ? 'yes' : 'no') . "\n\n";

if (! $valid) {
    echo "  ✗ Cannot proceed with invalid credentials.\n";
    exit(1);
}

// -- Publish a test post ------------------------------------------------------

echo "  Publishing test post to Facebook Page...\n";

$post = new Post(
    title: 'Hello from FoPost!',
    body: 'This is a real test post published to Facebook via the FoPost Social Core library. 🦉',
    url: 'https://fopost.com',
    tags: ['fopost', 'facebook', 'test'],
);

$result = $facebook->publish($post);
printResult($result);

// -- Show constraints ---------------------------------------------------------

echo "\n  Platform constraints:\n";
printConstraints($facebook->constraints());

echo "\nDone.\n";
