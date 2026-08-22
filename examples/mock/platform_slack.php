<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

/**
 * Slack Platform Integration Example
 *
 * Demonstrates how to use the Slack platform with both bot token
 * and incoming webhook modes, including Block Kit formatting.
 *
 * Requires: Bot token with `chat:write` scope, or an incoming webhook URL.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Http\Contracts\HttpClientInterface;
use Fopost\Social\Platforms\Slack\SlackPlatform;
use Fopost\Social\Platforms\Slack\SlackFormatter;

// -- Mock HTTP client for demonstration (replace with real HttpClient) --------

$mockHttp = new class implements HttpClientInterface {
    public function get(string $url, array $options = []): array
    {
        return ['status' => 200, 'headers' => [], 'body' => '{}'];
    }

    public function post(string $url, array $options = []): array
    {
        echo "  POST {$url}\n";

        // auth.test response
        if (str_contains($url, '/auth.test')) {
            return [
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'ok' => true,
                    'url' => 'https://myworkspace.slack.com/',
                    'team' => 'My Workspace',
                    'user' => 'fopost-bot',
                    'team_id' => 'T0123ABC',
                    'user_id' => 'U0123BOT',
                    'bot_id' => 'B0123BOT',
                ]),
            ];
        }

        // chat.delete response
        if (str_contains($url, '/chat.delete')) {
            return [
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'ok' => true,
                    'channel' => $options['json']['channel'] ?? 'C123',
                    'ts' => $options['json']['ts'] ?? '0',
                ]),
            ];
        }

        // Webhook response
        if (str_contains($url, 'hooks.slack.com')) {
            return [
                'status' => 200,
                'headers' => [],
                'body' => 'ok',
            ];
        }

        // chat.postMessage response
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'ok' => true,
                'channel' => $options['json']['channel'] ?? 'C123ABC',
                'ts' => '1700000000.' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
                'message' => ['text' => $options['json']['text'] ?? ''],
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

// =============================================================================
//  1. Bot Token Mode – Plain Text (mrkdwn)
// =============================================================================

echo "=== 1. Bot Token – Plain Text ===\n";

$botCredentials = new PlatformCredentials('slack', [
    'bot_token' => 'xoxb-your-bot-token',
    'channel' => 'C0123GENERAL',
]);

$slack = new SlackPlatform($botCredentials, $mockHttp);

$post = new Post(
    title: 'New Release: FoPost v2.0',
    body: 'We just shipped a major update with Slack integration and Block Kit support!',
    url: 'https://fopost.com/releases/v2',
    tags: ['release', 'fopost', 'slack'],
);

$result = $slack->publish($post);
echo "  Success: " . ($result->isSuccess() ? 'yes' : 'no') . "\n";
echo "  External ID: " . $result->externalId() . "\n\n";

// =============================================================================
//  2. Bot Token Mode – Block Kit
// =============================================================================

echo "=== 2. Bot Token – Block Kit ===\n";

$result = $slack->publish($post, ['blocks' => true]);
echo "  Success: " . ($result->isSuccess() ? 'yes' : 'no') . "\n";
echo "  External ID: " . $result->externalId() . "\n\n";

// =============================================================================
//  3. Bot Token – Thread Reply with Custom Identity
// =============================================================================

echo "=== 3. Bot Token – Thread Reply ===\n";

$replyPost = new Post(
    title: '',
    body: 'This is a threaded reply with a custom bot name and emoji icon.',
);

$result = $slack->publish($replyPost, [
    'thread_ts' => '1700000000.000001',
    'username' => 'FoPost Release Bot',
    'icon_emoji' => ':rocket:',
    'unfurl_links' => false,
]);
echo "  Success: " . ($result->isSuccess() ? 'yes' : 'no') . "\n";
echo "  External ID: " . $result->externalId() . "\n\n";

// =============================================================================
//  4. Webhook Mode
// =============================================================================

echo "=== 4. Webhook Mode ===\n";

$webhookCredentials = new PlatformCredentials('slack', [
    'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxxxxxxxxxxx',
]);

$webhookSlack = new SlackPlatform($webhookCredentials, $mockHttp);

$webhookPost = new Post(
    title: 'Webhook Notification',
    body: 'Automated deployment completed successfully.',
    tags: ['deploy', 'ci'],
);

$result = $webhookSlack->publish($webhookPost);
echo "  Success: " . ($result->isSuccess() ? 'yes' : 'no') . "\n";
echo "  External ID: " . $result->externalId() . "\n\n";

// =============================================================================
//  5. Validate Credentials
// =============================================================================

echo "=== 5. Validate Credentials ===\n";

echo "  Bot token valid: " . ($slack->validateCredentials() ? 'yes' : 'no') . "\n";
echo "  Webhook URL valid: " . ($webhookSlack->validateCredentials() ? 'yes' : 'no') . "\n\n";

// =============================================================================
//  6. Delete a Message (Bot Mode)
// =============================================================================

echo "=== 6. Delete Message ===\n";

$deleted = $slack->delete('C0123GENERAL:1700000000.000001');
echo "  Deleted: " . ($deleted ? 'yes' : 'no') . "\n\n";

// =============================================================================
//  7. Formatter Demo
// =============================================================================

echo "=== 7. Formatter Output ===\n";

$formatter = new SlackFormatter();

$formatterPost = new Post(
    title: 'Formatted Post',
    body: 'This demonstrates Slack mrkdwn formatting.',
    url: 'https://example.com',
    tags: ['demo', 'mrkdwn'],
);

echo "  Plain text:\n";
echo "  " . str_replace("\n", "\n  ", $formatter->format($formatterPost)) . "\n\n";

echo "  Block Kit (JSON structure):\n";
$blocks = $formatter->formatBlocks($formatterPost);
echo "  Blocks count: " . count($blocks) . "\n";
foreach ($blocks as $i => $block) {
    echo "    Block {$i}: type={$block['type']}\n";
}
echo "\n";

// =============================================================================
//  8. Constraints
// =============================================================================

echo "=== 8. Constraints ===\n";

$constraints = $slack->constraints();
foreach ($constraints as $key => $value) {
    $display = is_array($value) ? implode(', ', $value) : $value;
    echo "  {$key}: {$display}\n";
}
