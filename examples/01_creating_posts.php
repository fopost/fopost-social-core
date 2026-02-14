<?php

declare(strict_types=1);

/**
 * Example 01: Creating Posts
 *
 * The Post value object is the central piece of Owlstack Core.
 * Framework packages (Laravel, WordPress) build Post instances
 * from their own models and hand them to the Publisher.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Owlstack\Core\Content\Post;
use Owlstack\Core\Content\Media;
use Owlstack\Core\Content\MediaCollection;

echo "=== Example 01: Creating Posts ===\n\n";

// ── 1. Minimal post ─────────────────────────────────────────────────────
$post = new Post(
    title: 'Hello World',
    body: 'This is my very first post published with Owlstack Core!',
);

echo "1) Minimal post\n";
echo "   Title : {$post->title}\n";
echo "   Body  : {$post->body}\n";
echo "   hasUrl: " . ($post->hasUrl() ? 'yes' : 'no') . "\n";
echo "   hasMedia: " . ($post->hasMedia() ? 'yes' : 'no') . "\n\n";

// ── 2. Post with URL and tags ────────────────────────────────────────────
$post2 = new Post(
    title: 'New Blog Article',
    body: 'We just published our deep-dive into PHP 8.3 fibers and async.',
    url: 'https://example.com/blog/php83-fibers',
    tags: ['php', 'async', 'fibers'],
);

echo "2) Post with URL + tags\n";
echo "   URL  : {$post2->url}\n";
echo "   Tags : " . implode(', ', $post2->tags) . "\n";
echo "   hasUrl: " . ($post2->hasUrl() ? 'yes' : 'no') . "\n\n";

// ── 3. Post with excerpt (Twitter uses excerpt when available) ──────────
$post3 = new Post(
    title: 'Long-Form Article',
    body: str_repeat('Lorem ipsum dolor sit amet. ', 20),
    excerpt: 'A concise summary for tweet-sized platforms.',
    url: 'https://example.com/article',
);

echo "3) Post with excerpt\n";
echo "   Excerpt : {$post3->excerpt}\n";
echo "   Body len: " . mb_strlen($post3->body) . " chars\n\n";

// ── 4. Post with media ──────────────────────────────────────────────────
$image = new Media(
    path: '/tmp/photo.jpg',
    mimeType: 'image/jpeg',
    altText: 'A beautiful sunset',
    width: 1920,
    height: 1080,
    fileSize: 2_500_000,
);

$video = new Media(
    path: '/tmp/clip.mp4',
    mimeType: 'video/mp4',
    duration: 30,
    fileSize: 15_000_000,
);

$mediaCollection = new MediaCollection([$image, $video]);

$post4 = new Post(
    title: 'Check out our gallery!',
    body: 'Photos and videos from today\'s event.',
    media: $mediaCollection,
    tags: ['event', 'gallery'],
);

echo "4) Post with media\n";
echo "   hasMedia  : " . ($post4->hasMedia() ? 'yes' : 'no') . "\n";
echo "   Total media: {$post4->media->count()}\n";
echo "   Images     : {$post4->media->images()->count()}\n";
echo "   Videos     : {$post4->media->videos()->count()}\n\n";

// ── 5. Post with custom metadata ────────────────────────────────────────
$post5 = new Post(
    title: 'Metadata Demo',
    body: 'Posts can carry arbitrary metadata for framework-specific use.',
    metadata: [
        'wp_post_id' => 42,
        'eloquent_model' => 'App\\Models\\Article',
        'scheduled_at' => '2026-03-01 10:00:00',
    ],
);

echo "5) Post with metadata\n";
echo "   wp_post_id   : " . $post5->getMeta('wp_post_id') . "\n";
echo "   eloquent_model: " . $post5->getMeta('eloquent_model') . "\n";
echo "   missing_key   : " . ($post5->getMeta('missing', 'default_val')) . "\n\n";

echo "=== Done ===\n";
