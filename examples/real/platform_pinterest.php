<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

/**
 * Pinterest — Real API Example
 *
 * Creates a real pin on a Pinterest board via the Pinterest API v5.
 *
 * Required env vars:
 *   PINTEREST_ACCESS_TOKEN  — Pinterest API access token
 *   PINTEREST_BOARD_ID      — Target board ID
 *
 * Usage:
 *   export PINTEREST_ACCESS_TOKEN=...
 *   export PINTEREST_BOARD_ID=...
 *   php examples/real/platform_pinterest.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Http\HttpClient;
use Fopost\Social\Platforms\Pinterest\PinterestPlatform;

// -- Load credentials ---------------------------------------------------------

$accessToken = requireEnv('PINTEREST_ACCESS_TOKEN');
$boardId     = requireEnv('PINTEREST_BOARD_ID');

echo "=== Pinterest — Real API Example ===\n\n";

// -- Set up platform ----------------------------------------------------------

$credentials = new PlatformCredentials('pinterest', [
    'access_token' => $accessToken,
    'board_id'     => $boardId,
]);

$httpClient = new HttpClient();
$pinterest  = new PinterestPlatform($credentials, $httpClient);

// -- Validate credentials -----------------------------------------------------

echo "  Validating credentials...\n";
$valid = $pinterest->validateCredentials();
echo "  Credentials valid: " . ($valid ? 'yes' : 'no') . "\n\n";

if (! $valid) {
    echo "  ✗ Cannot proceed with invalid credentials.\n";
    exit(1);
}

// -- Publish a test pin -------------------------------------------------------

echo "  Creating test pin on Pinterest...\n";

$post = new Post(
    title: 'Hello from FoPost!',
    body: 'Testing the FoPost Social Core library — creating a pin on Pinterest. 🦉',
    url: 'https://fopost.com',
    tags: ['fopost', 'pinterest', 'test'],
);

$result = $pinterest->publish($post, [
    'image_url' => 'https://picsum.photos/1000/1500',
]);

printResult($result);

// -- Show constraints ---------------------------------------------------------

echo "\n  Platform constraints:\n";
printConstraints($pinterest->constraints());

echo "\nDone.\n";
