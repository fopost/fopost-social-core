<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

/**
 * Slack — Real API Example
 *
 * Sends a real message to a Slack channel via bot token or webhook.
 *
 * Required env vars (bot mode):
 *   SLACK_BOT_TOKEN  — Bot token (xoxb-...) with chat:write scope
 *   SLACK_CHANNEL    — Channel ID or name (e.g., C0123GENERAL or #general)
 *
 * OR (webhook mode):
 *   SLACK_WEBHOOK_URL — Incoming webhook URL
 *
 * Usage:
 *   export SLACK_BOT_TOKEN=xoxb-...
 *   export SLACK_CHANNEL=C0123GENERAL
 *   php examples/real/platform_slack.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';

use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Http\HttpClient;
use Owlstack\Core\Platforms\Slack\SlackPlatform;

// -- Load credentials ---------------------------------------------------------

$webhookUrl = optionalEnv('SLACK_WEBHOOK_URL');
$botToken   = optionalEnv('SLACK_BOT_TOKEN');
$channel    = optionalEnv('SLACK_CHANNEL');

if ($webhookUrl === '' && ($botToken === '' || $channel === '')) {
    echo "\n  ✗ Set either SLACK_WEBHOOK_URL, or both SLACK_BOT_TOKEN and SLACK_CHANNEL.\n";
    echo "    See .env.example for details.\n\n";
    exit(1);
}

echo "=== Slack — Real API Example ===\n\n";

// -- Set up platform ----------------------------------------------------------

if ($webhookUrl !== '') {
    $credentialData = ['webhook_url' => $webhookUrl];
    echo "  Mode: Webhook\n\n";
} else {
    $credentialData = ['bot_token' => $botToken, 'channel' => $channel];
    echo "  Mode: Bot Token (channel: {$channel})\n\n";
}

$credentials = new PlatformCredentials('slack', $credentialData);

$httpClient = new HttpClient();
$slack      = new SlackPlatform($credentials, $httpClient);

// -- Validate credentials -----------------------------------------------------

echo "  Validating credentials...\n";
$valid = $slack->validateCredentials();
echo "  Credentials valid: " . ($valid ? 'yes' : 'no') . "\n\n";

if (! $valid) {
    echo "  ✗ Cannot proceed with invalid credentials.\n";
    exit(1);
}

// -- Publish a test post ------------------------------------------------------

echo "  Sending test message to Slack...\n";

$post = new Post(
    title: 'Hello from Owlstack!',
    body: 'This is a real test message sent to Slack via the Owlstack Core library. 🦉',
    url: 'https://owlstack.dev',
    tags: ['owlstack', 'slack', 'test'],
);

$result = $slack->publish($post);
printResult($result);

// -- Show constraints ---------------------------------------------------------

echo "\n  Platform constraints:\n";
printConstraints($slack->constraints());

echo "\nDone.\n";
