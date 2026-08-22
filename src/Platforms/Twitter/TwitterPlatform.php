<?php

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-agnostic library; exceptions are not WordPress output.
// phpcs:disable WordPress.WP.AlternativeFunctions -- Framework-agnostic library; uses native PHP functions for portability.
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions -- base64_encode required for OAuth HMAC-SHA1 signature and media upload.

namespace Fopost\Social\Platforms\Twitter;

use Fopost\Social\Content\Media;
use Fopost\Social\Content\Post;
use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Exceptions\MediaValidationException;
use Fopost\Social\Exceptions\PlatformException;
use Fopost\Social\Exceptions\RateLimitException;
use Fopost\Social\Http\Contracts\HttpClientInterface;
use Fopost\Social\Platforms\Contracts\PlatformInterface;
use Fopost\Social\Platforms\Contracts\PlatformResponseInterface;
use Fopost\Social\Platforms\PlatformResponse;

/**
 * X/Twitter API v2 platform implementation.
 */
class TwitterPlatform implements PlatformInterface
{
    private const API_BASE_URL = 'https://api.x.com/2';
    private const UPLOAD_URL = 'https://upload.x.com/1.1/media/upload.json';
    private const MAX_TWEET_LENGTH = 280;
    private const MAX_MEDIA_COUNT = 4;
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024;      // 5MB
    private const MAX_VIDEO_SIZE = 512 * 1024 * 1024;     // 512MB
    private const MAX_RETRIES = 3;
    private const BASE_DELAY_SECONDS = 1;

    private const ALLOWED_MEDIA_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'video/mp4',
        'video/quicktime',
    ];

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        private readonly TwitterFormatter $formatter,
    ) {
    }

    public function name(): string
    {
        return 'twitter';
    }

    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        return $this->retryWithBackoff(function () use ($post, $options): PlatformResponseInterface {
            $text = $this->formatter->format($post);

            $params = ['text' => $text];

            // Handle media attachments
            if ($post->hasMedia()) {
                $mediaIds = [];
                foreach ($post->media->all() as $media) {
                    if (count($mediaIds) >= self::MAX_MEDIA_COUNT) {
                        break;
                    }
                    $mediaIds[] = $this->uploadMedia($media);
                }
                if ($mediaIds !== []) {
                    $params['media'] = ['media_ids' => $mediaIds];
                }
            }

            // Handle reply to tweet
            if (!empty($options['reply_to'])) {
                $params['reply'] = ['in_reply_to_tweet_id' => $options['reply_to']];
            }

            // Handle quote tweet
            if (!empty($options['quote_tweet_id'])) {
                $params['quote_tweet_id'] = $options['quote_tweet_id'];
            }

            // Handle poll
            if (!empty($options['poll'])) {
                $pollOptions = $options['poll']['options'] ?? [];
                if (count($pollOptions) >= 2 && count($pollOptions) <= 4) {
                    $params['poll'] = [
                        'options' => $pollOptions,
                        'duration_minutes' => $options['poll']['duration_minutes'] ?? 1440,
                    ];
                }
            }

            // Handle location
            if (!empty($options['place_id'])) {
                $params['geo'] = ['place_id' => $options['place_id']];
            }

            $url = self::API_BASE_URL . '/tweets';
            $response = $this->authenticatedRequest('POST', $url, $params);

            $data = json_decode($response['body'], true);

            $this->handleErrorResponse($response, $data);

            $tweetId = $data['data']['id'] ?? null;
            if ($tweetId === null) {
                return PlatformResponse::failure('Unexpected response format', $data ?? []);
            }

            return PlatformResponse::success(
                externalId: $tweetId,
                externalUrl: 'https://x.com/i/status/' . $tweetId,
                rawResponse: $data,
            );
        });
    }

    public function delete(string $externalId): bool
    {
        $url = self::API_BASE_URL . '/tweets/' . $externalId;
        $response = $this->authenticatedRequest('DELETE', $url);

        $data = json_decode($response['body'], true);

        if ($response['status'] !== 200) {
            return false;
        }

        return ($data['data']['deleted'] ?? false) === true;
    }

    public function validateCredentials(): bool
    {
        try {
            $url = self::API_BASE_URL . '/users/me';
            $response = $this->authenticatedRequest('GET', $url);

            $data = json_decode($response['body'], true);

            return $response['status'] === 200 && isset($data['data']['id']);
        } catch (\Throwable) {
            return false;
        }
    }

    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_TWEET_LENGTH,
            'max_media_count' => self::MAX_MEDIA_COUNT,
            'supported_media_types' => self::ALLOWED_MEDIA_TYPES,
            'max_media_size' => self::MAX_VIDEO_SIZE,
        ];
    }

    /**
     * Upload a media file to X's media upload endpoint.
     *
     * @return string The media_id_string from the upload response.
     * @throws MediaValidationException If the file type or size is invalid.
     * @throws PlatformException On upload failure.
     */
    private function uploadMedia(Media $media): string
    {
        // Validate media type
        if (!in_array($media->mimeType, self::ALLOWED_MEDIA_TYPES, true)) {
            throw new MediaValidationException(
                message: "Unsupported media type for X: {$media->mimeType}",
                platformName: 'twitter',
                mimeType: $media->mimeType,
                fileSize: $media->fileSize,
            );
        }

        // Validate file size
        $maxSize = str_starts_with($media->mimeType, 'video/') ? self::MAX_VIDEO_SIZE : self::MAX_IMAGE_SIZE;
        if ($media->fileSize !== null && $media->fileSize > $maxSize) {
            throw new MediaValidationException(
                message: 'Media file exceeds maximum size limit for X',
                platformName: 'twitter',
                mimeType: $media->mimeType,
                fileSize: $media->fileSize,
            );
        }

        if (!file_exists($media->path)) {
            throw new MediaValidationException(
                message: "Media file does not exist: {$media->path}",
                platformName: 'twitter',
                mimeType: $media->mimeType,
                fileSize: $media->fileSize,
            );
        }

        if (!is_readable($media->path)) {
            throw new MediaValidationException(
                message: "Media file is not readable: {$media->path}",
                platformName: 'twitter',
                mimeType: $media->mimeType,
                fileSize: $media->fileSize,
            );
        }

        $mediaData = base64_encode(file_get_contents($media->path));

        $response = $this->authenticatedRequest('POST', self::UPLOAD_URL, [
            'media_data' => $mediaData,
        ]);

        $data = json_decode($response['body'], true);

        $this->handleErrorResponse($response, $data);

        $mediaId = $data['media_id_string'] ?? null;
        if ($mediaId === null) {
            throw new PlatformException(
                message: 'Media upload returned no media_id_string',
                platformName: 'twitter',
                httpStatusCode: $response['status'],
                rawResponse: $data ?? [],
            );
        }

        return $mediaId;
    }

    /**
     * Send an authenticated request using OAuth 1.0a.
     *
     * @param string     $method HTTP method.
     * @param string     $url    Request URL.
     * @param array|null $body   JSON body (for POST/PUT).
     * @return array{status: int, headers: array, body: string}
     */
    private function authenticatedRequest(string $method, string $url, ?array $body = null): array
    {
        $authHeader = $this->buildOAuthHeader($method, $url);

        $options = [
            'headers' => [
                'Authorization' => $authHeader,
            ],
        ];

        if ($body !== null) {
            $options['json'] = $body;
            $options['headers']['Content-Type'] = 'application/json';
        }

        return match ($method) {
            'GET' => $this->httpClient->get($url, $options),
            'POST' => $this->httpClient->post($url, $options),
            'DELETE' => $this->httpClient->delete($url, $options),
            default => $this->httpClient->post($url, $options),
        };
    }

    /**
     * Build an OAuth 1.0a Authorization header.
     *
     * Implements the full HMAC-SHA1 signature flow as required by X API.
     *
     * @param string $method HTTP method.
     * @param string $url    Request URL.
     * @return string The complete 'OAuth ...' header value.
     */
    private function buildOAuthHeader(string $method, string $url): string
    {
        $consumerKey = $this->credentials->require('consumer_key');
        $consumerSecret = $this->credentials->require('consumer_secret');
        $accessToken = $this->credentials->require('access_token');
        $accessTokenSecret = $this->credentials->require('access_token_secret');

        $oauthParams = [
            'oauth_consumer_key' => $consumerKey,
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $accessToken,
            'oauth_version' => '1.0',
        ];

        // Build the signature base string
        $signatureParams = $oauthParams;
        ksort($signatureParams);

        $baseString = strtoupper($method) . '&'
            . rawurlencode($url) . '&'
            . rawurlencode(http_build_query($signatureParams));

        // Create the signing key
        $signingKey = rawurlencode($consumerSecret) . '&' . rawurlencode($accessTokenSecret);

        // Generate the signature
        $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
        $oauthParams['oauth_signature'] = $signature;

        // Build the header string
        $headerParts = [];
        foreach ($oauthParams as $key => $value) {
            $headerParts[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }

        return 'OAuth ' . implode(', ', $headerParts);
    }

    /**
     * Handle error responses from the X API.
     *
     * @throws RateLimitException On 429 responses.
     * @throws PlatformException On other error responses.
     */
    private function handleErrorResponse(array $response, ?array $data): void
    {
        if ($response['status'] === 429) {
            $resetTime = $response['headers']['x-rate-limit-reset'][0] ?? null;
            throw new RateLimitException(
                message: 'X API rate limit exceeded',
                platformName: 'twitter',
                retryAfter: $resetTime !== null
                    ? (new \DateTimeImmutable())->setTimestamp((int) $resetTime)
                    : null,
                httpStatusCode: 429,
                rawResponse: $data ?? [],
            );
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $errorMessage = $data['detail'] ?? $data['title'] ?? 'X API error';
            if (isset($data['errors'][0]['message'])) {
                $errorMessage = $data['errors'][0]['message'];
            }

            throw new PlatformException(
                message: $errorMessage,
                platformName: 'twitter',
                httpStatusCode: $response['status'],
                rawResponse: $data ?? [],
            );
        }
    }

    /**
     * Retry a callback with exponential backoff on rate limit errors.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws PlatformException After max retries exceeded.
     */
    private function retryWithBackoff(callable $callback, int $attempt = 0): mixed
    {
        try {
            return $callback();
        } catch (RateLimitException $e) {
            if ($attempt >= self::MAX_RETRIES) {
                throw $e;
            }

            $delay = self::BASE_DELAY_SECONDS * (2 ** $attempt);
            sleep($delay);

            return $this->retryWithBackoff($callback, $attempt + 1);
        }
    }
}
