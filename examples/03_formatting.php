<?php

declare(strict_types=1);

/**
 * Example 03: Formatting Utilities
 *
 * Shows CharacterTruncator, HashtagExtractor,
 * CanonicalLink, and platform-specific formatters.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Owlstack\Core\Content\CanonicalLink;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Formatting\CharacterTruncator;
use Owlstack\Core\Formatting\HashtagExtractor;
use Owlstack\Core\Platforms\Facebook\FacebookFormatter;
use Owlstack\Core\Platforms\Telegram\TelegramFormatter;
use Owlstack\Core\Platforms\Twitter\TwitterFormatter;

echo "=== Example 03: Formatting ===\n\n";

// ── 1. CharacterTruncator ───────────────────────────────────────────────
$truncator = new CharacterTruncator();

$shortText = 'Hello world';
$longText  = 'This is a substantially longer piece of text that will certainly exceed our chosen character limit and must be truncated smartly at a word boundary.';

echo "1) CharacterTruncator\n";
echo "   Short (limit 50) : " . $truncator->truncate($shortText, 50) . "\n";
echo "   Long  (limit 50) : " . $truncator->truncate($longText, 50) . "\n";
echo "   Long  (limit 80) : " . $truncator->truncate($longText, 80) . "\n";
echo "   Custom suffix     : " . $truncator->truncate($longText, 50, '...') . "\n";

$customTruncator = new CharacterTruncator('...');
echo "   Constructor suffix: " . $customTruncator->truncate($longText, 50) . "\n\n";

// ── 2. HashtagExtractor ─────────────────────────────────────────────────
$extractor = new HashtagExtractor();

echo "2) HashtagExtractor\n";
echo "   Basic    : " . $extractor->extract(['php', 'laravel', 'owlstack']) . "\n";
echo "   Prefixed : " . $extractor->extract(['#php', '#laravel']) . "\n";
echo "   Specials : " . $extractor->extract(['hello world', 'c++', 'node.js']) . "\n";
echo "   Max 2    : " . $extractor->extract(['a', 'b', 'c', 'd'], 2) . "\n";
echo "   Unicode  : " . $extractor->extract(['技術', 'プログラミング']) . "\n";
echo "   Empty    : '" . $extractor->extract([]) . "'\n\n";

// ── 3. CanonicalLink ────────────────────────────────────────────────────
echo "3) CanonicalLink\n";

$link = new CanonicalLink();
echo "   Default template : " . $link->generate('https://example.com/post/42') . "\n";

$custom = new CanonicalLink("\n🔗 {url}");
echo "   Custom template  : " . $custom->generate('https://example.com/post/42') . "\n";

// inject into content respecting max length
$content = 'A short text snippet about our new product launch.';
$url     = 'https://example.com/launch';
echo "   Inject (fits)    : " . $link->inject($content, $url, 200) . "\n";
echo "   Inject (tight)   : " . $link->inject($content, $url, 80) . "\n\n";

// ── 4. Platform Formatters ──────────────────────────────────────────────
$post = new Post(
    title: 'PHP 8.3 Released!',
    body: 'PHP 8.3 introduces typed class constants, the json_validate() function, and new Randomizer methods. A great step forward for the language.',
    url: 'https://example.com/php83',
    tags: ['php', 'php83', 'release'],
);

// Twitter (280 chars, t.co accounting)
$twitterFmt = new TwitterFormatter($extractor, $truncator);
echo "4a) Twitter Formatter (max {$twitterFmt->maxLength()} chars)\n";
$tweet = $twitterFmt->format($post);
echo "    Output ({" . mb_strlen($tweet) . "} chars):\n";
echo "    ----\n    " . str_replace("\n", "\n    ", $tweet) . "\n    ----\n\n";

// Telegram (text mode — 4096 chars)
$telegramFmt = new TelegramFormatter($extractor, $truncator);
echo "4b) Telegram Formatter (max {$telegramFmt->maxLength()} text / {$telegramFmt->maxLength(true)} caption)\n";
$tgText = $telegramFmt->format($post, ['is_caption' => false]);
echo "    Text mode ({" . mb_strlen($tgText) . "} chars):\n";
echo "    ----\n    " . str_replace("\n", "\n    ", $tgText) . "\n    ----\n\n";

$tgCaption = $telegramFmt->format($post, ['is_caption' => true]);
echo "    Caption mode ({" . mb_strlen($tgCaption) . "} chars):\n";
echo "    ----\n    " . str_replace("\n", "\n    ", $tgCaption) . "\n    ----\n\n";

// Facebook (63 206 chars)
$fbFmt = new FacebookFormatter($extractor, $truncator);
echo "4c) Facebook Formatter (max {$fbFmt->maxLength()} chars)\n";
$fbText = $fbFmt->format($post);
echo "    Output ({" . mb_strlen($fbText) . "} chars):\n";
echo "    ----\n    " . str_replace("\n", "\n    ", $fbText) . "\n    ----\n\n";

// ── 5. Truncation stress test ───────────────────────────────────────────
echo "5) Truncation stress test (very long body → Twitter)\n";
$longPost = new Post(
    title: 'Mega Post',
    body: str_repeat('This is a long sentence that keeps repeating itself. ', 20),
    url: 'https://example.com/mega',
    tags: ['long', 'test'],
);

$tweetLong = $twitterFmt->format($longPost);
echo "   Body length : " . mb_strlen($longPost->body) . " chars\n";
echo "   Tweet length: " . mb_strlen($tweetLong) . " chars\n";
echo "   Fits 280?   : " . (mb_strlen($tweetLong) <= 280 ? 'yes' : 'needs t.co accounting') . "\n\n";

echo "=== Done ===\n";
