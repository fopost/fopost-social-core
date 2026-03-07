<?php

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-agnostic library; exceptions are not WordPress output.
// phpcs:disable Universal.Operators.DisallowShortTernary.Found -- Short ternary used intentionally for concise null/empty fallbacks.

namespace Owlstack\Core\Platforms\Pinterest;

use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Exceptions\PlatformException;
use Owlstack\Core\Exceptions\RateLimitException;
use Owlstack\Core\Http\Contracts\HttpClientInterface;
use Owlstack\Core\Platforms\Contracts\PlatformInterface;
use Owlstack\Core\Platforms\Contracts\PlatformResponseInterface;
use Owlstack\Core\Platforms\PlatformResponse;

/**
 * Pinterest API v5 platform implementation.
 *
 * Creates Pins via `POST /v5/pins` with JSON body.
 * Deletes Pins via `DELETE /v5/pins/{pin_id}`.
 *
 * Supports:
 *   - Image Pins via public URL (`media_source.source_type = "image_url"`)
 *   - Video Pins via pre-uploaded media ID (`media_source.source_type = "video_id"`)
 *   - Board and board-section targeting
 *   - Link, title, description, alt text
 *
 * Required credentials:
 *   - `access_token` – OAuth 2.0 bearer token with `pins:write` scope
 *   - `board_id`     – Default board ID to pin to
 *
 * @see https://developers.pinterest.com/docs/api/v5/pins-create
 * @see https://developers.pinterest.com/docs/api/v5/pins-delete
 */
class PinterestPlatform implements PlatformInterface
{
    private const API_BASE_URL = 'https://api.pinterest.com/v5';

    /**
     * Maximum description length.
     */
    private const MAX_DESCRIPTION_LENGTH = 800;

    /**
     * Maximum title length.
     */
    private const MAX_TITLE_LENGTH = 100;

    /**
     * Maximum link URL length.
     */
    private const MAX_LINK_LENGTH = 2_048;

    /**
     * Maximum alt text length.
     */
    private const MAX_ALT_TEXT_LENGTH = 500;

    private readonly PinterestFormatter $formatter;

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        ?PinterestFormatter $formatter = null,
    ) {
        $this->formatter = $formatter ?? new PinterestFormatter();
    }

    public function name(): string
    {
        return 'pinterest';
    }

    /**
     * Publish a Pin to Pinterest.
     *
     * Options:
     *   - `board_id`         string  Board ID (overrides default credential).
     *   - `board_section_id` string  Board section ID.
     *   - `image_url`        string  Public URL of the image (required for image pins).
     *   - `media_id`         string  Pre-uploaded media ID (for video pins).
     *   - `cover_image_url`  string  Cover image URL (required with media_id).
     *   - `alt_text`         string  Accessibility alt text (max 500 chars).
     *   - `dominant_color`   string  Hex color, e.g. "#6E7874".
     *   - `is_standard`      bool    Standard pin (default true).
     *
     * @throws PlatformException  On API errors or missing media.
     * @throws RateLimitException When rate-limited.
     */
    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        $token = $this->credentials->require('access_token');
        $boardId = $options['board_id'] ?? $this->credentials->get('board_id');

        if ($boardId === null || $boardId === '') {
            return PlatformResponse::failure(
                'Pinterest requires a board_id to create a Pin.',
                [],
            );
        }

        $description = $this->formatter->format($post, $options);
        $title = $this->formatter->formatTitle($post);

        // Build request body
        $body = [
            'board_id' => (string) $boardId,
            'description' => $description,
        ];

        if ($title !== '') {
            $body['title'] = $title;
        }

        // Link (destination URL)
        if ($post->hasUrl()) {
            $body['link'] = mb_substr($post->url, 0, self::MAX_LINK_LENGTH);
        }

        // Alt text
        if (isset($options['alt_text'])) {
            $body['alt_text'] = mb_substr((string) $options['alt_text'], 0, self::MAX_ALT_TEXT_LENGTH);
        }

        // Dominant color
        if (isset($options['dominant_color'])) {
            $body['dominant_color'] = $options['dominant_color'];
        }

        // Board section
        if (isset($options['board_section_id'])) {
            $body['board_section_id'] = (string) $options['board_section_id'];
        }

        // Media source
        $body['media_source'] = $this->buildMediaSource($options);

        if ($body['media_source'] === null) {
            return PlatformResponse::failure(
                'Pinterest requires an image_url or media_id (video) to create a Pin.',
                [],
            );
        }

        $response = $this->httpClient->post(
            self::API_BASE_URL . '/pins',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ],
        );

        $data = json_decode($response['body'], true) ?: [];
        $this->handleErrorResponse($response, $data);

        $pinId = $data['id'] ?? null;

        if ($pinId === null) {
            return PlatformResponse::failure('Pinterest did not return a Pin ID.', $data);
        }

        return PlatformResponse::success(
            externalId: (string) $pinId,
            externalUrl: 'https://www.pinterest.com/pin/' . $pinId,
            rawResponse: $data,
        );
    }

    /**
     * Delete a Pin by its ID.
     *
     * Pinterest returns 204 No Content on success.
     */
    public function delete(string $externalId): bool
    {
        $token = $this->credentials->require('access_token');

        $response = $this->httpClient->delete(
            self::API_BASE_URL . '/pins/' . $externalId,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ],
        );

        return $response['status'] === 204;
    }

    /**
     * Validate credentials by querying the authenticated user's account.
     */
    public function validateCredentials(): bool
    {
        try {
            $token = $this->credentials->require('access_token');

            $response = $this->httpClient->get(
                self::API_BASE_URL . '/user_account',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                    ],
                ],
            );

            $data = json_decode($response['body'], true) ?: [];

            return $response['status'] === 200 && isset($data['username']);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *     max_text_length: int,
     *     max_title_length: int,
     *     max_link_length: int,
     *     max_alt_text_length: int,
     *     supported_media_types: string[],
     *     max_media_size: int,
     * }
     */
    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_DESCRIPTION_LENGTH,
            'max_title_length' => self::MAX_TITLE_LENGTH,
            'max_link_length' => self::MAX_LINK_LENGTH,
            'max_alt_text_length' => self::MAX_ALT_TEXT_LENGTH,
            'supported_media_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/quicktime'],
            'max_media_size' => 20 * 1024 * 1024, // 20 MB for images
        ];
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the media_source object for the Pin creation request.
     *
     * @return array|null Null if no valid media source found.
     */
    private function buildMediaSource(array $options): ?array
    {
        // Video pin (pre-uploaded via /v5/media)
        if (isset($options['media_id'])) {
            $source = [
                'source_type' => 'video_id',
                'media_id' => (string) $options['media_id'],
            ];

            if (isset($options['cover_image_url'])) {
                $source['cover_image_url'] = $options['cover_image_url'];
            }

            if (isset($options['is_standard'])) {
                $source['is_standard'] = (bool) $options['is_standard'];
            }

            return $source;
        }

        // Image pin via URL
        if (isset($options['image_url'])) {
            $source = [
                'source_type' => 'image_url',
                'url' => $options['image_url'],
            ];

            $source['is_standard'] = (bool) ($options['is_standard'] ?? true);

            return $source;
        }

        return null;
    }

    /**
     * Handle error responses from the Pinterest API.
     *
     * @throws RateLimitException On HTTP 429.
     * @throws PlatformException  On other API errors.
     */
    private function handleErrorResponse(array $response, ?array $data): void
    {
        if ($response['status'] >= 200 && $response['status'] < 300) {
            return;
        }

        $errorMessage = $data['message'] ?? $data['error'] ?? 'Pinterest API error';

        if ($response['status'] === 429) {
            throw new RateLimitException(
                message: (string) $errorMessage,
                platformName: 'pinterest',
                retryAfter: null,
                httpStatusCode: 429,
                rawResponse: $data ?? [],
            );
        }

        $errorCode = $data['code'] ?? null;

        throw new PlatformException(
            message: (string) $errorMessage,
            platformName: 'pinterest',
            httpStatusCode: $response['status'],
            apiErrorCode: $errorCode !== null ? (string) $errorCode : null,
            rawResponse: $data ?? [],
        );
    }
}
