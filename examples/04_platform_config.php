<?php

declare(strict_types=1);

/**
 * Example 04: Platform Configuration & Validation
 *
 * Shows PlatformCredentials, OwlstackConfig, and ConfigValidator.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Owlstack\Core\Config\ConfigValidator;
use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Config\OwlstackConfig;

echo "=== Example 04: Platform Configuration ===\n\n";

// ── 1. PlatformCredentials ──────────────────────────────────────────────
echo "1) PlatformCredentials\n";

$telegramCreds = new PlatformCredentials('telegram', [
    'api_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
    'channel_username' => '@my_channel',
]);

echo "   platform : {$telegramCreds->platform}\n";
echo "   api_token: " . $telegramCreds->get('api_token') . "\n";
echo "   has('api_token')         : " . ($telegramCreds->has('api_token') ? 'yes' : 'no') . "\n";
echo "   has('missing_key')       : " . ($telegramCreds->has('missing_key') ? 'yes' : 'no') . "\n";
echo "   get('missing', 'fallback'): " . $telegramCreds->get('missing', 'fallback') . "\n";
echo "   require('api_token')     : " . $telegramCreds->require('api_token') . "\n";

echo "\n   Trying require('nope')...\n";
try {
    $telegramCreds->require('nope');
} catch (\InvalidArgumentException $e) {
    echo "   Caught: {$e->getMessage()}\n";
}
echo "\n";

// ── 2. OwlstackConfig — central config container ────────────────────────
echo "2) OwlstackConfig\n";

$config = new OwlstackConfig(
    platforms: [
        'telegram' => [
            'api_token' => '123456:ABC-DEF',
            'channel_username' => '@test',
        ],
        'twitter' => [
            'consumer_key' => 'ck',
            'consumer_secret' => 'cs',
            'access_token' => 'at',
            'access_token_secret' => 'ats',
        ],
        'facebook' => [
            'app_id' => 'app123',
            'app_secret' => 'secret',
            'page_access_token' => 'pat',
            'page_id' => '9876',
        ],
    ],
    options: [
        'default_hashtag_count' => 5,
        'debug' => true,
    ],
);

echo "   Configured platforms: " . implode(', ', $config->configuredPlatforms()) . "\n";
echo "   hasPlatform('telegram') : " . ($config->hasPlatform('telegram') ? 'yes' : 'no') . "\n";
echo "   hasPlatform('linkedin') : " . ($config->hasPlatform('linkedin') ? 'yes' : 'no') . "\n";
echo "   option('debug')         : " . ($config->option('debug') ? 'true' : 'false') . "\n";
echo "   option('missing', 42)   : " . $config->option('missing', 42) . "\n";

$creds = $config->credentials('twitter');
echo "   twitter consumer_key    : " . $creds->get('consumer_key') . "\n\n";

// You can also pass pre-built PlatformCredentials instances:
$config2 = new OwlstackConfig(
    platforms: [
        'telegram' => $telegramCreds,
    ],
);
echo "   Pre-built credentials work: " . ($config2->hasPlatform('telegram') ? 'yes' : 'no') . "\n\n";

// ── 3. ConfigValidator ──────────────────────────────────────────────────
echo "3) ConfigValidator\n";

$validator = new ConfigValidator();

// Valid credentials
$missing = $validator->validate($config->credentials('telegram'));
echo "   Telegram valid? " . (empty($missing) ? 'yes' : 'missing: ' . implode(', ', $missing)) . "\n";

// Incomplete credentials
$badCreds = new PlatformCredentials('twitter', [
    'consumer_key' => 'ck',
    // missing consumer_secret, access_token, access_token_secret
]);
$missing = $validator->validate($badCreds);
echo "   Twitter incomplete, missing: " . implode(', ', $missing) . "\n";

// Register a custom platform
$validator->registerRequiredKeys('mastodon', ['instance_url', 'access_token']);
$mastodonCreds = new PlatformCredentials('mastodon', ['instance_url' => 'https://mastodon.social']);
$missing = $validator->validate($mastodonCreds);
echo "   Custom mastodon missing: " . implode(', ', $missing) . "\n";

// validateConfig — throws on error
echo "\n   Full config validation...\n";
try {
    $validator->validateConfig($config);
    echo "   Config is valid!\n";
} catch (\Owlstack\Core\Exceptions\OwlstackException $e) {
    echo "   Invalid: {$e->getMessage()}\n";
}

// Bad config
$badConfig = new OwlstackConfig([
    'twitter' => ['consumer_key' => 'ck'],
    'facebook' => [],
]);

echo "\n   Bad config validation...\n";
try {
    $validator->validateConfig($badConfig);
} catch (\Owlstack\Core\Exceptions\OwlstackException $e) {
    echo "   Caught: {$e->getMessage()}\n";
}

echo "\n=== Done ===\n";
