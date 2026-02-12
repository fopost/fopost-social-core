<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Tumblr;

use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Content\Post;
use Synglify\Core\Exceptions\PlatformException;
use Synglify\Core\Exceptions\RateLimitException;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\Contracts\PlatformInterface;
use Synglify\Core\Platforms\Contracts\PlatformResponseInterface;
use Synglify\Core\Platforms\PlatformResponse;

/**
 * Tumblr API v2 platform implementation using Neue Post Format (NPF).
 *
 * Creates posts via:
 *   POST /v2/blog/{blog-identifier}/posts
 *
 * Supports:
 *   - Text posts (with HTML body blocks)
 *   - Image posts (public URL via image content blocks)
 *   - Video posts (URL via video content blocks)
 *   - Link posts (via link content blocks)
 *   - Audio posts (URL via audio content blocks)
 *   - Tags (comma-separated)
 *
 * Required credentials:
 *   - `access_token`    – OAuth 2.0 Bearer token
 *   - `blog_identifier` – Blog name (e.g. "myblog" or "myblog.tumblr.com")
 *
 * @see https://www.tumblr.com/docs/en/api/v2
 */
class TumblrPlatform implements PlatformInterface
{
    private const API_BASE_URL = 'https://api.tumblr.com/v2';

    private readonly TumblrFormatter $formatter;

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        ?TumblrFormatter $formatter = null,
    ) {
        $this->formatter = $formatter ?? new TumblrFormatter();
    }

    public function name(): string
    {
        return 'tumblr';
    }

    /**
     * Publish a post to Tumblr using NPF.
     *
     * Options:
     *   - `post_type`    string  'text' (default), 'image', 'video', 'link', or 'audio'.
     *   - `image_url`    string  Public URL of the image (for image posts).
     *   - `video_url`    string  Public URL of the video (for video posts).
     *   - `audio_url`    string  Public URL of the audio (for audio posts).
     *   - `link_url`     string  URL for link posts (overrides $post->url).
     *   - `alt_text`     string  Alt text for image posts.
     *   - `state`        string  Post state: 'published' (default), 'draft', 'queue', or 'private'.
     *   - `slug`         string  Custom URL slug for the post.
     *   - `source_url`   string  Content source URL.
     *
     * @throws PlatformException  On API errors.
     * @throws RateLimitException When rate-limited.
     */
    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        $blogId = $this->credentials->require('blog_identifier');
        $token = $this->credentials->require('access_token');

        $postType = $options['post_type'] ?? 'text';

        $content = match ($postType) {
            'image' => $this->buildImageContent($post, $options),
            'video' => $this->buildVideoContent($post, $options),
            'audio' => $this->buildAudioContent($post, $options),
            'link' => $this->buildLinkContent($post, $options),
            default => $this->buildTextContent($post, $options),
        };

        $body = [
            'content' => $content,
        ];

        // Tags
        $tags = $this->formatter->formatTags($post);
        if ($tags !== '') {
            $body['tags'] = $tags;
        }

        // Post state
        if (isset($options['state'])) {
            $body['state'] = $options['state'];
        }

        // Custom slug
        if (isset($options['slug'])) {
            $body['slug'] = $options['slug'];
        }

        // Source URL
        if (isset($options['source_url'])) {
            $body['source_url'] = $options['source_url'];
        }

        $response = $this->httpClient->post(
            self::API_BASE_URL . '/blog/' . $blogId . '/posts',
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

        $postId = $data['response']['id'] ?? null;

        if ($postId === null) {
            return PlatformResponse::failure('Tumblr did not return a post ID.', $data);
        }

        $postId = (string) $postId;
        $postUrl = 'https://' . $blogId . '.tumblr.com/post/' . $postId;

        // If blog_identifier already contains .tumblr.com, use it directly
        if (str_contains($blogId, '.')) {
            $postUrl = 'https://' . $blogId . '/post/' . $postId;
        }

        return PlatformResponse::success(
            externalId: $postId,
            externalUrl: $postUrl,
            rawResponse: $data,
        );
    }

    /**
     * Delete a post from Tumblr.
     *
     * Uses POST /v2/blog/{blog-identifier}/post/delete with the post ID.
     */
    public function delete(string $externalId): bool
    {
        $blogId = $this->credentials->require('blog_identifier');
        $token = $this->credentials->require('access_token');

        $response = $this->httpClient->post(
            self::API_BASE_URL . '/blog/' . $blogId . '/post/delete',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'id' => $externalId,
                ],
            ],
        );

        $data = json_decode($response['body'], true) ?: [];

        if ($response['status'] >= 200 && $response['status'] < 300) {
            return true;
        }

        $this->handleErrorResponse($response, $data);

        return false;
    }

    /**
     * Validate credentials by querying blog info.
     */
    public function validateCredentials(): bool
    {
        try {
            $blogId = $this->credentials->require('blog_identifier');
            $token = $this->credentials->require('access_token');

            $response = $this->httpClient->get(
                self::API_BASE_URL . '/blog/' . $blogId . '/info',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                    ],
                ],
            );

            $data = json_decode($response['body'], true) ?: [];

            return $response['status'] === 200
                && isset($data['response']['blog']['name']);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *     max_text_length: int,
     *     max_title_length: int,
     *     supported_post_types: string[],
     *     max_tags: int,
     *     post_states: string[],
     * }
     */
    public function constraints(): array
    {
        return [
            'max_text_length' => 4_096,
            'max_title_length' => TumblrFormatter::MAX_TITLE_LENGTH,
            'supported_post_types' => ['text', 'image', 'video', 'link', 'audio'],
            'max_tags' => 30,
            'post_states' => ['published', 'draft', 'queue', 'private'],
        ];
    }

    // -------------------------------------------------------------------------
    //  Content Builders (NPF blocks)
    // -------------------------------------------------------------------------

    /**
     * Build text content blocks for a text post.
     */
    private function buildTextContent(Post $post, array $options): array
    {
        $blocks = [];

        // Title as heading block
        if ($post->title !== '') {
            $blocks[] = [
                'type' => 'text',
                'subtype' => 'heading1',
                'text' => $this->formatter->formatTitle($post),
            ];
        }

        // Body as text block(s)
        if ($post->body !== '') {
            $paragraphs = preg_split('/\n{2,}/', $post->body);
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if ($paragraph !== '') {
                    $blocks[] = [
                        'type' => 'text',
                        'text' => $paragraph,
                    ];
                }
            }
        }

        // URL as link text block
        if ($post->hasUrl()) {
            $blocks[] = [
                'type' => 'text',
                'subtype' => 'indented',
                'text' => $post->url,
                'formatting' => [
                    [
                        'start' => 0,
                        'end' => mb_strlen($post->url),
                        'type' => 'link',
                        'url' => $post->url,
                    ],
                ],
            ];
        }

        return $blocks;
    }

    /**
     * Build content blocks for an image post.
     */
    private function buildImageContent(Post $post, array $options): array
    {
        $imageUrl = $options['image_url'] ?? '';

        if ($imageUrl === '') {
            return $this->buildTextContent($post, $options);
        }

        $blocks = [];

        $imageBlock = [
            'type' => 'image',
            'media' => [
                ['url' => $imageUrl],
            ],
        ];

        if (isset($options['alt_text'])) {
            $imageBlock['alt_text'] = $options['alt_text'];
        }

        $blocks[] = $imageBlock;

        // Add caption as text block
        if ($post->body !== '' || $post->title !== '') {
            $captionText = $post->title !== ''
                ? $post->title . "\n\n" . $post->body
                : $post->body;

            $blocks[] = [
                'type' => 'text',
                'text' => trim($captionText),
            ];
        }

        return $blocks;
    }

    /**
     * Build content blocks for a video post.
     */
    private function buildVideoContent(Post $post, array $options): array
    {
        $videoUrl = $options['video_url'] ?? '';

        if ($videoUrl === '') {
            return $this->buildTextContent($post, $options);
        }

        $blocks = [];

        $blocks[] = [
            'type' => 'video',
            'url' => $videoUrl,
        ];

        // Add caption as text block
        if ($post->body !== '' || $post->title !== '') {
            $captionText = $post->title !== ''
                ? $post->title . "\n\n" . $post->body
                : $post->body;

            $blocks[] = [
                'type' => 'text',
                'text' => trim($captionText),
            ];
        }

        return $blocks;
    }

    /**
     * Build content blocks for an audio post.
     */
    private function buildAudioContent(Post $post, array $options): array
    {
        $audioUrl = $options['audio_url'] ?? '';

        if ($audioUrl === '') {
            return $this->buildTextContent($post, $options);
        }

        $blocks = [];

        $blocks[] = [
            'type' => 'audio',
            'url' => $audioUrl,
        ];

        // Add caption as text block
        if ($post->body !== '' || $post->title !== '') {
            $captionText = $post->title !== ''
                ? $post->title . "\n\n" . $post->body
                : $post->body;

            $blocks[] = [
                'type' => 'text',
                'text' => trim($captionText),
            ];
        }

        return $blocks;
    }

    /**
     * Build content blocks for a link post.
     */
    private function buildLinkContent(Post $post, array $options): array
    {
        $linkUrl = $options['link_url'] ?? $post->url ?? '';

        if ($linkUrl === '') {
            return $this->buildTextContent($post, $options);
        }

        $blocks = [];

        // Title as heading
        if ($post->title !== '') {
            $blocks[] = [
                'type' => 'text',
                'subtype' => 'heading1',
                'text' => $this->formatter->formatTitle($post),
            ];
        }

        // Link block
        $blocks[] = [
            'type' => 'link',
            'url' => $linkUrl,
        ];

        // Description as text
        if ($post->body !== '') {
            $blocks[] = [
                'type' => 'text',
                'text' => $post->body,
            ];
        }

        return $blocks;
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    /**
     * Handle error responses from the Tumblr API.
     *
     * @throws RateLimitException On HTTP 429.
     * @throws PlatformException  On other API errors.
     */
    private function handleErrorResponse(array $response, ?array $data): void
    {
        if ($response['status'] >= 200 && $response['status'] < 300) {
            return;
        }

        $meta = $data['meta'] ?? [];
        $errorMessage = $meta['msg'] ?? ($data['errors'][0]['detail'] ?? 'Tumblr API error');
        $errorCode = $meta['status'] ?? null;

        // Rate limit
        if ($response['status'] === 429) {
            $retryAfter = null;
            if (isset($response['headers']['Retry-After'])) {
                $seconds = (int) $response['headers']['Retry-After'];
                $retryAfter = new \DateTimeImmutable('+' . $seconds . ' seconds');
            }

            throw new RateLimitException(
                message: $errorMessage,
                platformName: 'tumblr',
                retryAfter: $retryAfter,
                httpStatusCode: 429,
                rawResponse: $data ?? [],
            );
        }

        throw new PlatformException(
            message: $errorMessage,
            platformName: 'tumblr',
            httpStatusCode: $response['status'],
            apiErrorCode: $errorCode !== null ? (string) $errorCode : null,
            rawResponse: $data ?? [],
        );
    }
}
