<?php

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-agnostic library; exceptions are not WordPress output.
// phpcs:disable Universal.Operators.DisallowShortTernary.Found -- Short ternary used intentionally for concise null/empty fallbacks.
// phpcs:disable WordPress.WP.AlternativeFunctions -- Framework-agnostic library; uses native PHP functions for portability.

namespace Fopost\Social\Platforms\Instagram;

use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Exceptions\PlatformException;
use Fopost\Social\Exceptions\RateLimitException;
use Fopost\Social\Http\Contracts\HttpClientInterface;
use Fopost\Social\Platforms\Contracts\PlatformInterface;
use Fopost\Social\Platforms\Contracts\PlatformResponseInterface;
use Fopost\Social\Platforms\PlatformResponse;

/**
 * Instagram Content Publishing API integration.
 *
 * Uses Meta's Instagram Graph API for publishing. Instagram requires a
 * **two-step** flow:
 *   1. Create a media container (`POST /<IG_USER_ID>/media`)
 *   2. Publish the container (`POST /<IG_USER_ID>/media_publish`)
 *
 * Supports:
 *   - Single image posts (public `image_url`)
 *   - Single video / Reels (`media_type=REELS`, public `video_url`)
 *   - Stories (`media_type=STORIES`)
 *   - Carousel posts (up to 10 items)
 *   - Caption-only posts are NOT supported by Instagram API (media required)
 *
 * Required credentials:
 *   - `access_token` – User access token with `instagram_basic` + `instagram_content_publish` scopes
 *   - `instagram_account_id` – The Instagram Professional Account ID (IG User ID)
 *
 * @see https://developers.facebook.com/docs/instagram-platform/content-publishing
 */
class InstagramPlatform implements PlatformInterface
{
    private const API_BASE_URL = 'https://graph.facebook.com';
    private const DEFAULT_GRAPH_VERSION = 'v19.0';

    /**
     * Maximum caption length.
     */
    private const MAX_CAPTION_LENGTH = 2_200;

    /**
     * Maximum carousel items.
     */
    private const MAX_CAROUSEL_ITEMS = 10;

    /**
     * Rate limit: 100 posts per 24 hours.
     */
    private const POSTS_PER_24H = 100;

    private readonly InstagramFormatter $formatter;

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        ?InstagramFormatter $formatter = null,
        private readonly string $graphVersion = self::DEFAULT_GRAPH_VERSION,
    ) {
        $this->formatter = $formatter ?? new InstagramFormatter();
    }

    public function name(): string
    {
        return 'instagram';
    }

    /**
     * Publish content to Instagram.
     *
     * Options:
     *   - `media_type`    string  'IMAGE' (default), 'REELS', or 'STORIES'.
     *   - `image_url`     string  Public URL to the image (required for IMAGE).
     *   - `video_url`     string  Public URL to the video (required for REELS/STORIES video).
     *   - `carousel`      array   Array of ['image_url' => ...] or ['video_url' => ...] for carousel items.
     *   - `location_id`   string  Facebook Location Page ID.
     *   - `user_tags`     array   Array of user tag objects [{username, x, y}].
     *   - `cover_url`     string  Public URL for reel cover image.
     *   - `share_to_feed` bool    Share reel to feed (default true).
     *   - `alt_text`      string  Alt text for accessibility.
     *
     * @throws PlatformException  On API errors or missing media.
     * @throws RateLimitException When rate-limited.
     */
    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        $igUserId = $this->credentials->require('instagram_account_id');
        $caption = $this->formatter->format($post, $options);

        // Determine publishing mode
        if (isset($options['carousel']) && is_array($options['carousel'])) {
            return $this->publishCarousel($igUserId, $caption, $options);
        }

        $mediaType = strtoupper($options['media_type'] ?? 'IMAGE');

        return match ($mediaType) {
            'REELS' => $this->publishReels($igUserId, $caption, $options),
            'STORIES' => $this->publishStory($igUserId, $caption, $options),
            default => $this->publishSingleImage($igUserId, $caption, $options),
        };
    }

    /**
     * Delete is not supported by Instagram's API.
     *
     * @throws PlatformException Always.
     */
    public function delete(string $externalId): bool
    {
        throw new PlatformException(
            'Instagram does not support deleting posts via the API.',
            'instagram',
        );
    }

    /**
     * Validate credentials by querying the Instagram account info.
     */
    public function validateCredentials(): bool
    {
        try {
            $igUserId = $this->credentials->require('instagram_account_id');
            $token = $this->credentials->require('access_token');

            $response = $this->httpClient->get(
                $this->graphApiUrl($igUserId),
                [
                    'query' => [
                        'fields' => 'id,username',
                        'access_token' => $token,
                    ],
                ],
            );

            $data = json_decode($response['body'], true) ?: [];

            return $response['status'] === 200 && isset($data['id']);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *     max_text_length: int,
     *     max_media_count: int,
     *     supported_media_types: string[],
     *     max_media_size: int,
     *     max_carousel_items: int,
     *     posts_per_24h: int,
     * }
     */
    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_CAPTION_LENGTH,
            'max_media_count' => self::MAX_CAROUSEL_ITEMS,
            'supported_media_types' => ['image/jpeg', 'video/mp4', 'video/quicktime'],
            'max_media_size' => 100 * 1024 * 1024, // 100 MB for video
            'max_carousel_items' => self::MAX_CAROUSEL_ITEMS,
            'posts_per_24h' => self::POSTS_PER_24H,
        ];
    }

    // -------------------------------------------------------------------------
    //  Single Image
    // -------------------------------------------------------------------------

    private function publishSingleImage(string $igUserId, string $caption, array $options): PlatformResponseInterface
    {
        $imageUrl = $options['image_url'] ?? null;

        if ($imageUrl === null || $imageUrl === '') {
            return PlatformResponse::failure(
                'Instagram requires an image_url for image posts. Media must be on a public server.',
                [],
            );
        }

        // Step 1: Create container
        $containerParams = [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $this->credentials->require('access_token'),
        ];

        $this->applyOptionalParams($containerParams, $options);

        $containerResponse = $this->httpClient->post(
            $this->graphApiUrl($igUserId . '/media'),
            ['form_params' => $containerParams],
        );

        $containerData = json_decode($containerResponse['body'], true) ?: [];
        $this->handleErrorResponse($containerResponse, $containerData);

        $containerId = $containerData['id'] ?? null;
        if ($containerId === null) {
            return PlatformResponse::failure('Failed to create media container.', $containerData);
        }

        // Step 2: Publish
        return $this->publishContainer($igUserId, $containerId);
    }

    // -------------------------------------------------------------------------
    //  Reels
    // -------------------------------------------------------------------------

    private function publishReels(string $igUserId, string $caption, array $options): PlatformResponseInterface
    {
        $videoUrl = $options['video_url'] ?? null;

        if ($videoUrl === null || $videoUrl === '') {
            return PlatformResponse::failure(
                'Instagram requires a video_url for Reels posts.',
                [],
            );
        }

        $containerParams = [
            'media_type' => 'REELS',
            'video_url' => $videoUrl,
            'caption' => $caption,
            'access_token' => $this->credentials->require('access_token'),
        ];

        if (isset($options['cover_url'])) {
            $containerParams['cover_url'] = $options['cover_url'];
        }
        if (isset($options['share_to_feed'])) {
            $containerParams['share_to_feed'] = $options['share_to_feed'] ? 'true' : 'false';
        }

        $this->applyOptionalParams($containerParams, $options);

        $containerResponse = $this->httpClient->post(
            $this->graphApiUrl($igUserId . '/media'),
            ['form_params' => $containerParams],
        );

        $containerData = json_decode($containerResponse['body'], true) ?: [];
        $this->handleErrorResponse($containerResponse, $containerData);

        $containerId = $containerData['id'] ?? null;
        if ($containerId === null) {
            return PlatformResponse::failure('Failed to create Reels container.', $containerData);
        }

        return $this->publishContainer($igUserId, $containerId);
    }

    // -------------------------------------------------------------------------
    //  Stories
    // -------------------------------------------------------------------------

    private function publishStory(string $igUserId, string $caption, array $options): PlatformResponseInterface
    {
        $containerParams = [
            'media_type' => 'STORIES',
            'access_token' => $this->credentials->require('access_token'),
        ];

        if (isset($options['image_url'])) {
            $containerParams['image_url'] = $options['image_url'];
        } elseif (isset($options['video_url'])) {
            $containerParams['video_url'] = $options['video_url'];
        } else {
            return PlatformResponse::failure(
                'Instagram Stories require either image_url or video_url.',
                [],
            );
        }

        $containerResponse = $this->httpClient->post(
            $this->graphApiUrl($igUserId . '/media'),
            ['form_params' => $containerParams],
        );

        $containerData = json_decode($containerResponse['body'], true) ?: [];
        $this->handleErrorResponse($containerResponse, $containerData);

        $containerId = $containerData['id'] ?? null;
        if ($containerId === null) {
            return PlatformResponse::failure('Failed to create Stories container.', $containerData);
        }

        return $this->publishContainer($igUserId, $containerId);
    }

    // -------------------------------------------------------------------------
    //  Carousel
    // -------------------------------------------------------------------------

    private function publishCarousel(string $igUserId, string $caption, array $options): PlatformResponseInterface
    {
        $items = array_slice($options['carousel'], 0, self::MAX_CAROUSEL_ITEMS);
        $token = $this->credentials->require('access_token');
        $childIds = [];

        // Step 1: Create individual item containers
        foreach ($items as $item) {
            $itemParams = [
                'is_carousel_item' => 'true',
                'access_token' => $token,
            ];

            if (isset($item['video_url'])) {
                $itemParams['media_type'] = 'VIDEO';
                $itemParams['video_url'] = $item['video_url'];
            } elseif (isset($item['image_url'])) {
                $itemParams['image_url'] = $item['image_url'];
            } else {
                continue; // Skip items without media
            }

            $itemResponse = $this->httpClient->post(
                $this->graphApiUrl($igUserId . '/media'),
                ['form_params' => $itemParams],
            );

            $itemData = json_decode($itemResponse['body'], true) ?: [];
            $this->handleErrorResponse($itemResponse, $itemData);

            if (isset($itemData['id'])) {
                $childIds[] = $itemData['id'];
            }
        }

        if ($childIds === []) {
            return PlatformResponse::failure('No valid carousel items created.', []);
        }

        // Step 2: Create carousel container
        $carouselParams = [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childIds),
            'caption' => $caption,
            'access_token' => $token,
        ];

        $this->applyOptionalParams($carouselParams, $options);

        $carouselResponse = $this->httpClient->post(
            $this->graphApiUrl($igUserId . '/media'),
            ['form_params' => $carouselParams],
        );

        $carouselData = json_decode($carouselResponse['body'], true) ?: [];
        $this->handleErrorResponse($carouselResponse, $carouselData);

        $carouselId = $carouselData['id'] ?? null;
        if ($carouselId === null) {
            return PlatformResponse::failure('Failed to create carousel container.', $carouselData);
        }

        // Step 3: Publish
        return $this->publishContainer($igUserId, $carouselId);
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    /**
     * Publish a media container (Step 2 of the two-step flow).
     */
    private function publishContainer(string $igUserId, string $containerId): PlatformResponseInterface
    {
        $response = $this->httpClient->post(
            $this->graphApiUrl($igUserId . '/media_publish'),
            [
                'form_params' => [
                    'creation_id' => $containerId,
                    'access_token' => $this->credentials->require('access_token'),
                ],
            ],
        );

        $data = json_decode($response['body'], true) ?: [];
        $this->handleErrorResponse($response, $data);

        $mediaId = $data['id'] ?? null;

        if ($mediaId === null) {
            return PlatformResponse::failure('Failed to publish media container.', $data);
        }

        return PlatformResponse::success(
            externalId: (string) $mediaId,
            externalUrl: 'https://www.instagram.com/p/' . $mediaId,
            rawResponse: $data,
        );
    }

    /**
     * Apply optional container creation parameters.
     */
    private function applyOptionalParams(array &$params, array $options): void
    {
        $optionalKeys = ['location_id', 'alt_text'];
        foreach ($optionalKeys as $key) {
            if (isset($options[$key])) {
                $params[$key] = $options[$key];
            }
        }

        if (isset($options['user_tags']) && is_array($options['user_tags'])) {
            $params['user_tags'] = json_encode($options['user_tags']);
        }
    }

    /**
     * Build a Graph API URL.
     */
    private function graphApiUrl(string $path): string
    {
        return self::API_BASE_URL . '/' . $this->graphVersion . '/' . ltrim($path, '/');
    }

    /**
     * Handle error responses from the Instagram Graph API.
     *
     * @throws RateLimitException On rate limit errors (code 4, 32, or HTTP 429).
     * @throws PlatformException  On other API errors.
     */
    private function handleErrorResponse(array $response, ?array $data): void
    {
        if ($response['status'] >= 200 && $response['status'] < 300) {
            return;
        }

        $errorMessage = $data['error']['message'] ?? 'Instagram API error';
        $errorCode = $data['error']['code'] ?? null;

        // Rate limit: Graph API uses code 4 (app-level) or 32 (page-level)
        if ($response['status'] === 429 || $errorCode === 4 || $errorCode === 32) {
            throw new RateLimitException(
                message: $errorMessage,
                platformName: 'instagram',
                retryAfter: null,
                httpStatusCode: $response['status'],
                rawResponse: $data ?? [],
            );
        }

        throw new PlatformException(
            message: $errorMessage,
            platformName: 'instagram',
            httpStatusCode: $response['status'],
            apiErrorCode: $errorCode !== null ? (string) $errorCode : null,
            rawResponse: $data ?? [],
        );
    }
}
