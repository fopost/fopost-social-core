<?php

declare(strict_types=1);

/**
 * Example 07: Error Handling
 *
 * Walks through every exception in the Synglify hierarchy
 * and shows how to catch them at different levels.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Synglify\Core\Exceptions\AuthenticationException;
use Synglify\Core\Exceptions\ContentTooLongException;
use Synglify\Core\Exceptions\MediaValidationException;
use Synglify\Core\Exceptions\PlatformException;
use Synglify\Core\Exceptions\RateLimitException;
use Synglify\Core\Exceptions\SynglifyException;

echo "=== Example 07: Error Handling ===\n\n";

// ── 1. Base SynglifyException ───────────────────────────────────────────
echo "1) SynglifyException (base)\n";
try {
    throw new SynglifyException('Something went wrong in Synglify');
} catch (SynglifyException $e) {
    echo "   Message: {$e->getMessage()}\n\n";
}

// ── 2. PlatformException ────────────────────────────────────────────────
echo "2) PlatformException\n";
try {
    throw new PlatformException(
        message: 'API returned an error',
        platformName: 'facebook',
        httpStatusCode: 400,
        apiErrorCode: 'OAuthException',
        rawResponse: ['error' => ['message' => 'Invalid token', 'code' => 190]],
    );
} catch (PlatformException $e) {
    echo "   Message       : {$e->getMessage()}\n";
    echo "   platformName  : {$e->platformName}\n";
    echo "   httpStatusCode: {$e->httpStatusCode}\n";
    echo "   apiErrorCode  : {$e->apiErrorCode}\n";
    echo "   rawResponse   : " . json_encode($e->rawResponse) . "\n";
    echo "   Is SynglifyException? " . ($e instanceof SynglifyException ? 'yes' : 'no') . "\n\n";
}

// ── 3. RateLimitException ───────────────────────────────────────────────
echo "3) RateLimitException\n";
try {
    $retryAt = new DateTimeImmutable('+60 seconds');
    throw new RateLimitException(
        message: 'Rate limit exceeded',
        platformName: 'twitter',
        retryAfter: $retryAt,
        httpStatusCode: 429,
    );
} catch (RateLimitException $e) {
    echo "   Message       : {$e->getMessage()}\n";
    echo "   platformName  : {$e->platformName}\n";
    echo "   httpStatusCode: {$e->httpStatusCode}\n";
    echo "   retryAfter    : {$e->retryAfter->format('Y-m-d H:i:s')}\n";
    echo "   retryAfterSec : ~{$e->retryAfterSeconds()}s\n";
    echo "   Is PlatformException? " . ($e instanceof PlatformException ? 'yes' : 'no') . "\n\n";
}

// ── 4. AuthenticationException ──────────────────────────────────────────
echo "4) AuthenticationException\n";
try {
    throw new AuthenticationException('Invalid or expired access token for Telegram');
} catch (AuthenticationException $e) {
    echo "   Message: {$e->getMessage()}\n";
    echo "   Is SynglifyException? " . ($e instanceof SynglifyException ? 'yes' : 'no') . "\n\n";
}

// ── 5. ContentTooLongException ──────────────────────────────────────────
echo "5) ContentTooLongException\n";
try {
    throw new ContentTooLongException(
        platformName: 'twitter',
        maxLength: 280,
        actualLength: 350,
    );
} catch (ContentTooLongException $e) {
    echo "   Message     : {$e->getMessage()}\n";
    echo "   platformName: {$e->platformName}\n";
    echo "   maxLength   : {$e->maxLength}\n";
    echo "   actualLength: {$e->actualLength}\n\n";
}

// ── 6. MediaValidationException ─────────────────────────────────────────
echo "6) MediaValidationException\n";
try {
    throw new MediaValidationException(
        message: 'Unsupported media type for Twitter',
        platformName: 'twitter',
        mimeType: 'image/webp',
        fileSize: 12_000_000,
    );
} catch (MediaValidationException $e) {
    echo "   Message     : {$e->getMessage()}\n";
    echo "   platformName: {$e->platformName}\n";
    echo "   mimeType    : {$e->mimeType}\n";
    echo "   fileSize    : {$e->fileSize} bytes\n\n";
}

// ── 7. Catching at the base level ───────────────────────────────────────
echo "7) Polymorphic catch — all Synglify exceptions\n";
$exceptions = [
    new SynglifyException('base error'),
    new PlatformException('api error', 'telegram', 500),
    new RateLimitException('rate limited', 'twitter'),
    new AuthenticationException('bad token'),
    new ContentTooLongException('twitter', 280, 350),
    new MediaValidationException('bad file', 'facebook', 'image/bmp', 999),
];

foreach ($exceptions as $ex) {
    try {
        throw $ex;
    } catch (SynglifyException $e) {
        $class = (new ReflectionClass($e))->getShortName();
        echo "   {$class}: {$e->getMessage()}\n";
    }
}

echo "\n=== Done ===\n";
