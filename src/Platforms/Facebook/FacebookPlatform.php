<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Facebook;

use Synglify\Core\Content\Post;
use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Exceptions\MediaValidationException;
use Synglify\Core\Exceptions\PlatformException;
use Synglify\Core\Exceptions\RateLimitException;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\Contracts\PlatformInterface;
use Synglify\Core\Platforms\Contracts\PlatformResponseInterface;
use Synglify\Core\Platforms\PlatformResponse;

/**
 * Facebook Graph API platform implementation.
 *
 * Posts content to a Facebook Page using the Graph API. Supports link
 * sharing, photo uploads, and video uploads. Does not depend on the
 * Facebook PHP SDK — all calls go through HttpClientInterface.
 */
class FacebookPlatform implements PlatformInterface
{
    private const API_BASE_URL = 'https://graph.facebook.com';
    private const DEFAULT_GRAPH_VERSION = 'v19.0';
    private const MAX_POST_LENGTH = 63206;

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        private readonly FacebookFormatter $formatter,
        private readonly string $graphVersion = self::DEFAULT_GRAPH_VERSION,
    ) {
    }

    public function name(): string
    {
        return 'facebook';
    }

    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        $message = $this->formatter->format($post);
        $pageId = $this->credentials->require('page_id');
        $token = $this->credentials->require('page_access_token');

        // Determine post type based on content
        if ($post->hasMedia()) {
            $media = $post->media->first();

            if ($media->isVideo()) {
                return $this->publishVideo($post, $message, $pageId, $token, $options);
            }

            if ($media->isImage()) {
                return $this->publishPhoto($post, $message, $pageId, $token, $options);
            }
        }

        // Default: link or text post via the feed endpoint
        return $this->publishToFeed($post, $message, $pageId, $token, $options);
    }

    public function delete(string $externalId): bool
    {
        $token = $this->credentials->require('page_access_token');

        $url = $this->graphApiUrl($externalId);
        $response = $this->httpClient->delete($url, [
            'query' => ['access_token' => $token],
        ]);

        $data = json_decode($response['body'], true);

        if ($response['status'] !== 200) {
            return false;
        }

        return ($data['success'] ?? false) === true;
    }

    public function validateCredentials(): bool
    {
        try {
            $appId = $this->credentials->require('app_id');
            $appSecret = $this->credentials->require('app_secret');
            $pageAccessToken = $this->credentials->require('page_access_token');

            // Verify the page access token via debug_token endpoint
            $response = $this->httpClient->get(
                self::API_BASE_URL . '/debug_token',
                [
                    'query' => [
                        'input_token' => $pageAccessToken,
                        'access_token' => $appId . '|' . $appSecret,
                    ],
                ]
            );

            $data = json_decode($response['body'], true);

            if ($response['status'] !== 200) {
                return false;
            }

            return ($data['data']['is_valid'] ?? false) === true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_POST_LENGTH,
            'max_media_count' => 1,
            'supported_media_types' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/bmp',
                'image/tiff',
                'video/mp4',
                'video/avi',
                'video/quicktime',
            ],
            'max_media_size' => 1024 * 1024 * 1024, // 1GB for video
        ];
    }

    /**
     * Publish a text or link post to the page feed.
     */
    private function publishToFeed(Post $post, string $message, string $pageId, string $token, array $options): PlatformResponseInterface
    {
        $url = $this->graphApiUrl($pageId . '/feed');

        $params = [
            'message' => $message,
            'access_token' => $token,
        ];

        // Attach link if the post has a URL
        if ($post->hasUrl()) {
            $params['link'] = $post->url;
        }

        $this->applyPublishOptions($params, $options);

        $response = $this->httpClient->post($url, [
            'form_params' => $params,
        ]);

        $data = json_decode($response['body'], true);
        $this->handleErrorResponse($response, $data);

        return $this->buildResponse($data);
    }

    /**
     * Publish a photo post to the page.
     */
    private function publishPhoto(Post $post, string $message, string $pageId, string $token, array $options): PlatformResponseInterface
    {
        $url = $this->graphApiUrl($pageId . '/photos');
        $media = $post->media->first();

        $this->assertFileReadable($media->path);

        $multipart = [
            ['name' => 'message', 'contents' => $message],
            ['name' => 'access_token', 'contents' => $token],
            [
                'name' => 'source',
                'contents' => file_get_contents($media->path),
                'filename' => basename($media->path),
                'headers' => ['Content-Type' => $media->mimeType],
            ],
        ];

        // Add publish options as multipart fields
        $optionParams = [];
        $this->applyPublishOptions($optionParams, $options);
        foreach ($optionParams as $key => $value) {
            $multipart[] = ['name' => $key, 'contents' => is_array($value) ? json_encode($value) : (string) $value];
        }

        $response = $this->httpClient->post($url, [
            'multipart' => $multipart,
        ]);

        $data = json_decode($response['body'], true);
        $this->handleErrorResponse($response, $data);

        return $this->buildResponse($data);
    }

    /**
     * Publish a video post to the page.
     */
    private function publishVideo(Post $post, string $message, string $pageId, string $token, array $options): PlatformResponseInterface
    {
        $url = $this->graphApiUrl($pageId . '/videos');
        $media = $post->media->first();

        $this->assertFileReadable($media->path);

        $multipart = [
            ['name' => 'description', 'contents' => $message],
            ['name' => 'title', 'contents' => $post->title],
            ['name' => 'access_token', 'contents' => $token],
            [
                'name' => 'source',
                'contents' => file_get_contents($media->path),
                'filename' => basename($media->path),
                'headers' => ['Content-Type' => $media->mimeType],
            ],
        ];

        // Add publish options as multipart fields
        $optionParams = [];
        $this->applyPublishOptions($optionParams, $options);
        foreach ($optionParams as $key => $value) {
            $multipart[] = ['name' => $key, 'contents' => is_array($value) ? json_encode($value) : (string) $value];
        }

        $response = $this->httpClient->post($url, [
            'multipart' => $multipart,
        ]);

        $data = json_decode($response['body'], true);
        $this->handleErrorResponse($response, $data);

        return $this->buildResponse($data);
    }

    /**
     * Assert that a file exists and is readable before attempting to read it.
     *
     * @throws MediaValidationException If the file does not exist or is not readable.
     */
    private function assertFileReadable(string $path): void
    {
        if (!file_exists($path)) {
            throw new MediaValidationException(
                message: "Media file does not exist: {$path}",
                platformName: 'facebook',
            );
        }

        if (!is_readable($path)) {
            throw new MediaValidationException(
                message: "Media file is not readable: {$path}",
                platformName: 'facebook',
            );
        }
    }

    /**
     * Apply optional publish parameters (privacy, targeting, scheduling).
     *
     * @param array $params  Target parameters array (modified by reference).
     * @param array $options User-provided options.
     */
    private function applyPublishOptions(array &$params, array $options): void
    {
        if (isset($options['privacy'])) {
            $params['privacy'] = json_encode($options['privacy']);
        }

        if (isset($options['targeting'])) {
            $params['targeting'] = json_encode($options['targeting']);
        }

        if (isset($options['scheduled_publish_time'])) {
            $params['published'] = 'false';
            $params['scheduled_publish_time'] = $options['scheduled_publish_time'];
        }
    }

    /**
     * Build a Graph API URL for the given path.
     */
    private function graphApiUrl(string $path): string
    {
        return self::API_BASE_URL . '/' . $this->graphVersion . '/' . ltrim($path, '/');
    }

    /**
     * Handle error responses from the Graph API.
     *
     * @throws RateLimitException On rate limit errors.
     * @throws PlatformException On other API errors.
     */
    private function handleErrorResponse(array $response, ?array $data): void
    {
        if ($response['status'] === 429) {
            throw new RateLimitException(
                message: $data['error']['message'] ?? 'Facebook API rate limit exceeded',
                platformName: 'facebook',
                retryAfter: null,
                httpStatusCode: 429,
                rawResponse: $data ?? [],
            );
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $errorMessage = $data['error']['message'] ?? 'Facebook API error';
            $errorCode = $data['error']['code'] ?? null;

            throw new PlatformException(
                message: $errorMessage,
                platformName: 'facebook',
                httpStatusCode: $response['status'],
                apiErrorCode: $errorCode !== null ? (string) $errorCode : null,
                rawResponse: $data ?? [],
            );
        }
    }

    /**
     * Build a PlatformResponse from a successful Graph API response.
     */
    private function buildResponse(array $data): PlatformResponseInterface
    {
        $postId = $data['id'] ?? $data['post_id'] ?? null;

        if ($postId === null) {
            return PlatformResponse::failure('Unexpected response format', $data);
        }

        return PlatformResponse::success(
            externalId: (string) $postId,
            externalUrl: 'https://www.facebook.com/' . $postId,
            rawResponse: $data,
        );
    }
}
