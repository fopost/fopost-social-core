<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

/**
 * Instagram — Real API Example
 *
 * Publishes a real image post to Instagram via the Content Publishing API.
 *
 * Required env vars:
 *   INSTAGRAM_ACCESS_TOKEN   — Facebook Graph API access token
 *   INSTAGRAM_ACCOUNT_ID     — Instagram Business/Creator Account ID
 *
 * Optional env vars:
 *   INSTAGRAM_TEST_IMAGE_URL — A publicly accessible image URL (defaults to picsum.photos)
 *
 * Usage:
 *   export INSTAGRAM_ACCESS_TOKEN=...
 *   export INSTAGRAM_ACCOUNT_ID=...
 *   php examples/real/platform_instagram.php
 *
 * Note: Instagram requires media to be hosted at publicly accessible URLs.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Http\HttpClient;
use Fopost\Social\Platforms\Instagram\InstagramPlatform;

// -- Load credentials ---------------------------------------------------------

$accessToken = requireEnv('INSTAGRAM_ACCESS_TOKEN');
$accountId   = requireEnv('INSTAGRAM_ACCOUNT_ID');
$imageUrl    = optionalEnv('INSTAGRAM_TEST_IMAGE_URL', 'https://picsum.photos/1080/1080');

echo "=== Instagram — Real API Example ===\n\n";

// -- Set up platform ----------------------------------------------------------

$credentials = new PlatformCredentials('instagram', [
    'access_token'         => $accessToken,
    'instagram_account_id' => $accountId,
]);

$httpClient = new HttpClient();
$instagram  = new InstagramPlatform($credentials, $httpClient);

// -- Validate credentials -----------------------------------------------------

echo "  Validating credentials...\n";
$valid = $instagram->validateCredentials();
echo "  Credentials valid: " . ($valid ? 'yes' : 'no') . "\n\n";

if (! $valid) {
    echo "  ✗ Cannot proceed with invalid credentials.\n";
    exit(1);
}

// -- Publish a test post ------------------------------------------------------

echo "  Publishing test image to Instagram...\n";
echo "  Image URL: {$imageUrl}\n";

$post = new Post(
    title: 'Hello from Owlstack!',
    body: 'Testing the Owlstack Core library — publishing to Instagram. 🦉',
    tags: ['owlstack', 'instagram', 'test'],
);

$result = $instagram->publish($post, [
    'media_type' => 'IMAGE',
    'image_url'  => $imageUrl,
]);

printResult($result);

// -- Show constraints ---------------------------------------------------------

echo "\n  Platform constraints:\n";
printConstraints($instagram->constraints());

echo "\nDone.\n";
