<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

declare(strict_types=1);

/**
 * Example 02: Media Handling
 *
 * Demonstrates Media objects, MediaCollection filtering,
 * immutable add(), and iteration.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Fopost\Social\Content\Media;
use Fopost\Social\Content\MediaCollection;

echo "=== Example 02: Media Handling ===\n\n";

// ── 1. Creating individual media items ──────────────────────────────────
$photo = new Media(
    path: '/uploads/sunset.jpg',
    mimeType: 'image/jpeg',
    altText: 'Sunset over mountains',
    width: 3840,
    height: 2160,
    fileSize: 4_200_000,
);

$gif = new Media(
    path: '/uploads/reaction.gif',
    mimeType: 'image/gif',
    fileSize: 800_000,
);

$video = new Media(
    path: '/uploads/demo.mp4',
    mimeType: 'video/mp4',
    duration: 120,
    fileSize: 25_000_000,
);

$podcast = new Media(
    path: '/uploads/episode42.mp3',
    mimeType: 'audio/mpeg',
    duration: 3600,
    fileSize: 50_000_000,
);

$pdf = new Media(
    path: '/uploads/whitepaper.pdf',
    mimeType: 'application/pdf',
    fileSize: 1_500_000,
);

echo "1) Type detection\n";
echo "   sunset.jpg   → isImage: " . ($photo->isImage() ? 'yes' : 'no') . "\n";
echo "   reaction.gif → isImage: " . ($gif->isImage() ? 'yes' : 'no') . "\n";
echo "   demo.mp4     → isVideo: " . ($video->isVideo() ? 'yes' : 'no') . "\n";
echo "   episode42.mp3→ isAudio: " . ($podcast->isAudio() ? 'yes' : 'no') . "\n";
echo "   whitepaper   → isDoc  : " . ($pdf->isDocument() ? 'yes' : 'no') . "\n\n";

// ── 2. Building a collection ────────────────────────────────────────────
$collection = new MediaCollection([$photo, $gif, $video, $podcast]);

echo "2) Collection basics\n";
echo "   Count   : {$collection->count()}\n";
echo "   isEmpty : " . ($collection->isEmpty() ? 'yes' : 'no') . "\n";
echo "   First   : {$collection->first()->path}\n\n";

// ── 3. Immutable add — returns a new collection ─────────────────────────
$withPdf = $collection->add($pdf);

echo "3) Immutable add()\n";
echo "   Original count: {$collection->count()}\n";
echo "   New count     : {$withPdf->count()}\n\n";

// ── 4. Filtering ────────────────────────────────────────────────────────
echo "4) Filtering\n";
$images = $collection->images();
$videos = $collection->videos();
echo "   Images: {$images->count()} — ";
foreach ($images as $img) {
    echo basename($img->path) . ' ';
}
echo "\n";

echo "   Videos: {$videos->count()} — ";
foreach ($videos as $vid) {
    echo basename($vid->path) . ' ';
}
echo "\n\n";

// ── 5. Iterating ────────────────────────────────────────────────────────
echo "5) Iterating over all media\n";
foreach ($withPdf as $i => $media) {
    $type = match (true) {
        $media->isImage() => 'IMAGE',
        $media->isVideo() => 'VIDEO',
        $media->isAudio() => 'AUDIO',
        default => 'DOC',
    };
    $size = $media->fileSize ? number_format($media->fileSize / 1_000_000, 1) . ' MB' : 'n/a';
    echo "   [{$i}] {$type} — {$media->path} ({$size})\n";
}
echo "\n";

// ── 6. Empty collection ─────────────────────────────────────────────────
$empty = new MediaCollection();
echo "6) Empty collection\n";
echo "   isEmpty: " . ($empty->isEmpty() ? 'yes' : 'no') . "\n";
echo "   first() : " . ($empty->first() === null ? 'null' : $empty->first()->path) . "\n\n";

echo "=== Done ===\n";
