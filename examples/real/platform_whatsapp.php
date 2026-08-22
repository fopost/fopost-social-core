<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

/**
 * WhatsApp — Real API Example
 *
 * Sends a real message to a WhatsApp number via the Cloud API.
 *
 * Required env vars:
 *   WHATSAPP_ACCESS_TOKEN     — Facebook Graph API access token
 *   WHATSAPP_PHONE_NUMBER_ID  — Your WhatsApp Business phone number ID
 *   WHATSAPP_TO               — Recipient phone number in E.164 format (e.g., +1234567890)
 *
 * Usage:
 *   export WHATSAPP_ACCESS_TOKEN=...
 *   export WHATSAPP_PHONE_NUMBER_ID=...
 *   export WHATSAPP_TO=+1234567890
 *   php examples/real/platform_whatsapp.php
 *
 * Note: The recipient must have opted in to receive messages from your business.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Http\HttpClient;
use Fopost\Social\Platforms\WhatsApp\WhatsAppPlatform;

// -- Load credentials ---------------------------------------------------------

$accessToken   = requireEnv('WHATSAPP_ACCESS_TOKEN');
$phoneNumberId = requireEnv('WHATSAPP_PHONE_NUMBER_ID');
$to            = requireEnv('WHATSAPP_TO');

echo "=== WhatsApp — Real API Example ===\n\n";
echo "  Sending to: {$to}\n\n";

// -- Set up platform ----------------------------------------------------------

$credentials = new PlatformCredentials('whatsapp', [
    'access_token'    => $accessToken,
    'phone_number_id' => $phoneNumberId,
]);

$httpClient = new HttpClient();
$whatsapp   = new WhatsAppPlatform($credentials, $httpClient);

// -- Validate credentials -----------------------------------------------------

echo "  Validating credentials...\n";
$valid = $whatsapp->validateCredentials();
echo "  Credentials valid: " . ($valid ? 'yes' : 'no') . "\n\n";

if (! $valid) {
    echo "  ✗ Cannot proceed with invalid credentials.\n";
    exit(1);
}

// -- Publish a test message ---------------------------------------------------

echo "  Sending test message via WhatsApp...\n";

$post = new Post(
    title: 'Hello from FoPost!',
    body: 'This is a real test message sent via the FoPost Social Core library. 🦉',
    url: 'https://fopost.com',
);

$result = $whatsapp->publish($post, [
    'to'           => $to,
    'message_type' => 'text',
    'preview_url'  => true,
]);

printResult($result);

// -- Show constraints ---------------------------------------------------------

echo "\n  Platform constraints:\n";
printConstraints($whatsapp->constraints());

echo "\nDone.\n";
