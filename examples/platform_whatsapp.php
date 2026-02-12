<?php

/**
 * WhatsApp Platform Integration Example
 *
 * Demonstrates how to use the WhatsApp Cloud API platform to send
 * text, image, video, document, and template messages.
 *
 * Requires: Meta developer access token with whatsapp_business_messaging permission,
 *           and a WhatsApp Business Phone Number ID.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Content\Post;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\WhatsApp\WhatsAppFormatter;
use Synglify\Core\Platforms\WhatsApp\WhatsAppPlatform;

// -- Mock HTTP client for demonstration (replace with real HttpClient) --------

$mockHttp = new class implements HttpClientInterface {
    public function get(string $url, array $options = []): array
    {
        echo "  GET {$url}\n";

        // Phone number validation response
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'id' => '123456789',
                'display_phone_number' => '+14155238886',
                'verified_name' => 'My Business',
            ]),
        ];
    }

    public function post(string $url, array $options = []): array
    {
        echo "  POST {$url}\n";
        echo '  Body: ' . json_encode($options['json'] ?? [], JSON_PRETTY_PRINT) . "\n";

        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'messaging_product' => 'whatsapp',
                'contacts' => [
                    ['input' => $options['json']['to'] ?? '', 'wa_id' => ltrim($options['json']['to'] ?? '', '+')],
                ],
                'messages' => [
                    ['id' => 'wamid.' . bin2hex(random_bytes(16))],
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

// -- Credentials --------------------------------------------------------------

$credentials = new PlatformCredentials('whatsapp', [
    'access_token' => 'EAAG...your-access-token...',
    'phone_number_id' => '123456789',
]);

$platform = new WhatsAppPlatform($credentials, $mockHttp);
$formatter = new WhatsAppFormatter();
$recipient = '+14155238886'; // E.164 format

// =============================================================================
//  1. Platform Info
// =============================================================================

echo "=== WhatsApp Platform ===\n\n";
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
    title: 'Product Launch',
    body: 'We are excited to announce our new product! Check it out.',
    url: 'https://example.com/launch',
    tags: ['launch', 'product', 'announcement'],
);

echo "Platform    : {$formatter->platform()}\n";
echo "Max length  : {$formatter->maxLength()}\n";
echo "Formatted   :\n{$formatter->format($post)}\n\n";
echo "Caption     :\n{$formatter->formatCaption($post)}\n\n";

// =============================================================================
//  4. Send Text Message
// =============================================================================

echo "=== Text Message ===\n\n";

$textPost = new Post(
    title: 'Hello from Synglify',
    body: 'This is a text message with a link preview.',
    url: 'https://example.com',
);

$result = $platform->publish($textPost, ['to' => $recipient]);
echo 'Success    : ' . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Message ID : {$result->externalId()}\n\n";

// =============================================================================
//  5. Send Image Message
// =============================================================================

echo "=== Image Message ===\n\n";

$imagePost = new Post(
    title: 'Check this out',
    body: 'Amazing photo from our event.',
);

$result = $platform->publish($imagePost, [
    'to' => $recipient,
    'message_type' => 'image',
    'image_url' => 'https://example.com/photo.jpg',
]);
echo 'Success    : ' . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Message ID : {$result->externalId()}\n\n";

// =============================================================================
//  6. Send Video Message
// =============================================================================

echo "=== Video Message ===\n\n";

$videoPost = new Post(
    title: 'Product Demo',
    body: 'Watch our 2-minute product demo.',
);

$result = $platform->publish($videoPost, [
    'to' => $recipient,
    'message_type' => 'video',
    'video_url' => 'https://example.com/demo.mp4',
]);
echo 'Success    : ' . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Message ID : {$result->externalId()}\n\n";

// =============================================================================
//  7. Send Document Message
// =============================================================================

echo "=== Document Message ===\n\n";

$docPost = new Post(
    title: 'Monthly Report',
    body: 'Here is the monthly report for January 2025.',
);

$result = $platform->publish($docPost, [
    'to' => $recipient,
    'message_type' => 'document',
    'document_url' => 'https://example.com/report.pdf',
    'filename' => 'january-2025-report.pdf',
]);
echo 'Success    : ' . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Message ID : {$result->externalId()}\n\n";

// =============================================================================
//  8. Send Template Message
// =============================================================================

echo "=== Template Message ===\n\n";

$templatePost = new Post(title: '', body: '');

$result = $platform->publish($templatePost, [
    'to' => $recipient,
    'message_type' => 'template',
    'template_name' => 'hello_world',
    'template_lang' => 'en',
    'template_components' => [
        [
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => 'John'],
            ],
        ],
    ],
]);
echo 'Success    : ' . ($result->isSuccess() ? 'Yes' : 'No') . "\n";
echo "Message ID : {$result->externalId()}\n\n";

// =============================================================================
//  9. Delete (Not Supported)
// =============================================================================

echo "=== Delete (Not Supported) ===\n\n";

try {
    $platform->delete('wamid.test123');
} catch (\Synglify\Core\Exceptions\PlatformException $e) {
    echo "Expected error: {$e->getMessage()}\n\n";
}

echo "Done!\n";
