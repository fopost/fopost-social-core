<?php

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-agnostic library; exceptions are not WordPress output.
// phpcs:disable WordPress.WP.AlternativeFunctions -- Framework-agnostic library; uses native PHP functions for portability.
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions -- urlencode required for URN encoding in API calls.

namespace Owlstack\Core\Platforms\LinkedIn;

use Owlstack\Core\Content\Post;
use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Exceptions\MediaValidationException;
use Owlstack\Core\Exceptions\PlatformException;
use Owlstack\Core\Exceptions\RateLimitException;
use Owlstack\Core\Http\Contracts\HttpClientInterface;
use Owlstack\Core\Platforms\Contracts\PlatformInterface;
use Owlstack\Core\Platforms\Contracts\PlatformResponseInterface;
use Owlstack\Core\Platforms\PlatformResponse;

/**
 * LinkedIn API platform implementation.
 *
 * Posts content to LinkedIn using the Community Management API.
 * Supports text posts, article shares (with URLs), and image posts.
 * Uses OAuth 2.0 Bearer token authentication.
 */
class LinkedInPlatform implements PlatformInterface
{
    private const API_BASE_URL = 'https://api.linkedin.com/rest';
    private const MAX_POST_LENGTH = 3000;
    private const MAX_IMAGE_SIZE = 8 * 1024 * 1024; // 8MB
    private const API_VERSION = '202401';

    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
    ];

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        private readonly LinkedInFormatter $formatter,
    ) {
    }

    public function name(): string
    {
        return 'linkedin';
    }

    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        $commentary = $this->formatter->format($post);
        $authorUrn = $this->getAuthorUrn();
        $token = $this->credentials->require('access_token');

        if ($post->hasMedia()) {
            $media = $post->media->first();

            if ($media->isImage()) {
                return $this->publishImagePost($post, $commentary, $authorUrn, $token, $options);
            }
        }

        // Default: text or article post
        return $this->publishTextPost($post, $commentary, $authorUrn, $token, $options);
    }

    public function delete(string $externalId): bool
    {
        $token = $this->credentials->require('access_token');

        $url = self::API_BASE_URL . '/posts/' . urlencode($externalId);
        $response = $this->httpClient->delete($url, [
            'headers' => $this->buildHeaders($token),
        ]);

        return $response['status'] >= 200 && $response['status'] < 300;
    }

    public function validateCredentials(): bool
    {
        try {
            $token = $this->credentials->require('access_token');

            $response = $this->httpClient->get(
                self::API_BASE_URL . '/userinfo',
                [
                    'headers' => $this->buildHeaders($token),
                ]
            );

            $data = json_decode($response['body'], true);

            return $response['status'] === 200 && isset($data['sub']);
        } catch (\Throwable) {
            return false;
        }
    }

    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_POST_LENGTH,
            'max_media_count' => 1,
            'supported_media_types' => self::ALLOWED_IMAGE_TYPES,
            'max_media_size' => self::MAX_IMAGE_SIZE,
        ];
    }

    /**
     * Publish a text-only or article (URL) post.
     */
    private function publishTextPost(Post $post, string $commentary, string $authorUrn, string $token, array $options): PlatformResponseInterface
    {
        $url = self::API_BASE_URL . '/posts';

        $body = [
            'author' => $authorUrn,
            'commentary' => $commentary,
            'visibility' => $options['visibility'] ?? 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
        ];

        // If the post has a URL, attach it as an article
        if ($post->hasUrl()) {
            $body['content'] = [
                'article' => [
                    'source' => $post->url,
                    'title' => $post->title,
                ],
            ];
        }

        $response = $this->httpClient->post($url, [
            'headers' => $this->buildHeaders($token),
            'json' => $body,
        ]);

        $data = json_decode($response['body'], true);
        $this->handleErrorResponse($response, $data);

        return $this->buildResponseFromHeaders($response);
    }

    /**
     * Publish a post with an image attachment.
     *
     * LinkedIn image posting requires a multi-step flow:
     * 1. Initialize the upload to get an upload URL.
     * 2. Upload the binary image to the upload URL.
     * 3. Create the post referencing the uploaded image.
     */
    private function publishImagePost(Post $post, string $commentary, string $authorUrn, string $token, array $options): PlatformResponseInterface
    {
        $media = $post->media->first();

        $this->validateMedia($media);

        // Step 1: Initialize upload
        $imageUrn = $this->initializeImageUpload($authorUrn, $token);

        // Step 2: Upload the image binary
        $this->uploadImageBinary($imageUrn['uploadUrl'], $media->path, $token);

        // Step 3: Create the post with the image reference
        $url = self::API_BASE_URL . '/posts';

        $body = [
            'author' => $authorUrn,
            'commentary' => $commentary,
            'visibility' => $options['visibility'] ?? 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'content' => [
                'media' => [
                    'id' => $imageUrn['imageUrn'],
                ],
            ],
        ];

        $response = $this->httpClient->post($url, [
            'headers' => $this->buildHeaders($token),
            'json' => $body,
        ]);

        $data = json_decode($response['body'], true);
        $this->handleErrorResponse($response, $data);

        return $this->buildResponseFromHeaders($response);
    }

    /**
     * Initialize an image upload with the LinkedIn Images API.
     *
     * @return array{imageUrn: string, uploadUrl: string}
     * @throws PlatformException On API errors.
     */
    private function initializeImageUpload(string $authorUrn, string $token): array
    {
        $url = self::API_BASE_URL . '/images?action=initializeUpload';

        $body = [
            'initializeUploadRequest' => [
                'owner' => $authorUrn,
            ],
        ];

        $response = $this->httpClient->post($url, [
            'headers' => $this->buildHeaders($token),
            'json' => $body,
        ]);

        $data = json_decode($response['body'], true);
        $this->handleErrorResponse($response, $data);

        $uploadUrl = $data['value']['uploadUrl'] ?? null;
        $imageUrn = $data['value']['image'] ?? null;

        if ($uploadUrl === null || $imageUrn === null) {
            throw new PlatformException(
                message: 'LinkedIn image upload initialization returned unexpected format',
                platformName: 'linkedin',
                httpStatusCode: $response['status'],
                rawResponse: $data ?? [],
            );
        }

        return [
            'imageUrn' => $imageUrn,
            'uploadUrl' => $uploadUrl,
        ];
    }

    /**
     * Upload the raw image binary to LinkedIn's upload URL.
     *
     * @throws PlatformException On upload failure.
     */
    private function uploadImageBinary(string $uploadUrl, string $filePath, string $token): void
    {
        $response = $this->httpClient->put($uploadUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => file_get_contents($filePath),
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $data = json_decode($response['body'], true);
            throw new PlatformException(
                message: 'LinkedIn image binary upload failed',
                platformName: 'linkedin',
                httpStatusCode: $response['status'],
                rawResponse: $data ?? [],
            );
        }
    }

    /**
     * Validate a media file before uploading to LinkedIn.
     *
     * @throws MediaValidationException If the file is invalid.
     */
    private function validateMedia(\Owlstack\Core\Content\Media $media): void
    {
        if (!in_array($media->mimeType, self::ALLOWED_IMAGE_TYPES, true)) {
            throw new MediaValidationException(
                message: "Unsupported media type for LinkedIn: {$media->mimeType}",
                platformName: 'linkedin',
                mimeType: $media->mimeType,
                fileSize: $media->fileSize,
            );
        }

        if ($media->fileSize !== null && $media->fileSize > self::MAX_IMAGE_SIZE) {
            throw new MediaValidationException(
                message: 'Media file exceeds maximum size limit for LinkedIn (8MB)',
                platformName: 'linkedin',
                mimeType: $media->mimeType,
                fileSize: $media->fileSize,
            );
        }

        if (!file_exists($media->path)) {
            throw new MediaValidationException(
                message: "Media file does not exist: {$media->path}",
                platformName: 'linkedin',
                mimeType: $media->mimeType,
                fileSize: $media->fileSize,
            );
        }

        if (!is_readable($media->path)) {
            throw new MediaValidationException(
                message: "Media file is not readable: {$media->path}",
                platformName: 'linkedin',
                mimeType: $media->mimeType,
                fileSize: $media->fileSize,
            );
        }
    }

    /**
     * Build the author URN from credentials.
     *
     * Supports both person URNs (urn:li:person:ID) and organization URNs
     * (urn:li:organization:ID). If a raw ID is provided, defaults to person.
     */
    private function getAuthorUrn(): string
    {
        // Check for organization_id first (company pages)
        if ($this->credentials->has('organization_id')) {
            $orgId = $this->credentials->require('organization_id');
            return str_starts_with($orgId, 'urn:li:') ? $orgId : 'urn:li:organization:' . $orgId;
        }

        // Default to person (personal profiles)
        $personId = $this->credentials->require('person_id');
        return str_starts_with($personId, 'urn:li:') ? $personId : 'urn:li:person:' . $personId;
    }

    /**
     * Build common request headers for LinkedIn API calls.
     *
     * @return array<string, string>
     */
    private function buildHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version' => self::API_VERSION,
        ];
    }

    /**
     * Handle error responses from the LinkedIn API.
     *
     * @throws RateLimitException On 429 responses.
     * @throws PlatformException On other error responses.
     */
    private function handleErrorResponse(array $response, ?array $data): void
    {
        if ($response['status'] === 429) {
            throw new RateLimitException(
                message: $data['message'] ?? 'LinkedIn API rate limit exceeded',
                platformName: 'linkedin',
                retryAfter: null,
                httpStatusCode: 429,
                rawResponse: $data ?? [],
            );
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $errorMessage = $data['message'] ?? $data['error'] ?? 'LinkedIn API error';
            $errorCode = $data['status'] ?? null;

            throw new PlatformException(
                message: is_string($errorMessage) ? $errorMessage : 'LinkedIn API error',
                platformName: 'linkedin',
                httpStatusCode: $response['status'],
                apiErrorCode: $errorCode !== null ? (string) $errorCode : null,
                rawResponse: $data ?? [],
            );
        }
    }

    /**
     * Build a PlatformResponse from the LinkedIn API response.
     *
     * LinkedIn returns the post URN in the x-restli-id header on 201 Created.
     */
    private function buildResponseFromHeaders(array $response): PlatformResponseInterface
    {
        $postId = $response['headers']['x-restli-id'][0]
            ?? $response['headers']['X-RestLi-Id'][0]
            ?? null;

        // Fallback: try to get from response body
        if ($postId === null) {
            $data = json_decode($response['body'], true);
            $postId = $data['id'] ?? null;
        }

        if ($postId === null) {
            return PlatformResponse::failure(
                'LinkedIn API returned no post identifier',
                json_decode($response['body'], true) ?? [],
            );
        }

        // LinkedIn post URLs follow the format: https://www.linkedin.com/feed/update/{urn}
        $externalUrl = 'https://www.linkedin.com/feed/update/' . urlencode($postId);

        return PlatformResponse::success(
            externalId: $postId,
            externalUrl: $externalUrl,
            rawResponse: json_decode($response['body'], true) ?? [],
        );
    }
}
