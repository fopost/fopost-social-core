<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Reddit;

use Synglify\Core\Content\Post;
use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Exceptions\PlatformException;
use Synglify\Core\Exceptions\RateLimitException;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\Contracts\PlatformInterface;
use Synglify\Core\Platforms\Contracts\PlatformResponseInterface;
use Synglify\Core\Platforms\PlatformResponse;

/**
 * Reddit API platform implementation.
 *
 * Publishes content to Reddit using the Reddit OAuth API. Supports
 * self-posts (text) and link-posts (URL). Posts are submitted to a
 * specified subreddit.
 *
 * Reddit API requires:
 * - OAuth 2.0 bearer token for authentication
 * - A descriptive User-Agent header (enforced by Reddit)
 * - Rate limit compliance: 60 requests/minute with OAuth
 *
 * @see https://www.reddit.com/dev/api/
 * @see https://github.com/reddit-archive/reddit/wiki/OAuth2
 */
class RedditPlatform implements PlatformInterface
{
    private const API_BASE_URL = 'https://oauth.reddit.com';
    private const AUTH_URL = 'https://www.reddit.com/api/v1/access_token';
    private const MAX_TITLE_LENGTH = 300;
    private const MAX_BODY_LENGTH = 40000;
    private const USER_AGENT_PREFIX = 'Synglify/1.0';

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        private readonly RedditFormatter $formatter,
    ) {
    }

    public function name(): string
    {
        return 'reddit';
    }

    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        $token = $this->credentials->require('access_token');
        $subreddit = $options['subreddit'] ?? $this->credentials->get('subreddit');

        if ($subreddit === null || $subreddit === '') {
            throw new PlatformException(
                message: 'Reddit requires a subreddit to publish to. Provide it via options[\'subreddit\'] or credentials[\'subreddit\'].',
                platformName: 'reddit',
                httpStatusCode: 0,
            );
        }

        // Determine post type: link or self
        $isLinkPost = ($options['kind'] ?? null) === 'link'
            || (($options['kind'] ?? null) === null && $post->hasUrl() && $post->body === '');

        if ($isLinkPost) {
            return $this->publishLinkPost($post, $subreddit, $token, $options);
        }

        return $this->publishSelfPost($post, $subreddit, $token, $options);
    }

    public function delete(string $externalId): bool
    {
        $token = $this->credentials->require('access_token');

        $response = $this->httpClient->post(
            self::API_BASE_URL . '/api/del',
            [
                'headers' => $this->buildHeaders($token),
                'form_params' => [
                    'id' => $externalId,
                ],
            ]
        );

        // Reddit returns empty JSON object on success
        return $response['status'] === 200;
    }

    public function validateCredentials(): bool
    {
        try {
            $token = $this->credentials->require('access_token');

            $response = $this->httpClient->get(
                self::API_BASE_URL . '/api/v1/me',
                [
                    'headers' => $this->buildHeaders($token),
                ]
            );

            $data = json_decode($response['body'], true);

            if ($response['status'] !== 200) {
                return false;
            }

            return isset($data['name']);
        } catch (\Throwable) {
            return false;
        }
    }

    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_BODY_LENGTH,
            'max_title_length' => self::MAX_TITLE_LENGTH,
            'max_media_count' => 1,
            'supported_media_types' => [
                'image/jpeg',
                'image/png',
                'image/gif',
            ],
            'max_media_size' => 20 * 1024 * 1024, // 20MB
        ];
    }

    /**
     * Publish a self-post (text post) to a subreddit.
     */
    private function publishSelfPost(Post $post, string $subreddit, string $token, array $options): PlatformResponseInterface
    {
        $title = $this->formatter->formatTitle($post);
        $body = $this->formatter->format($post);

        $params = [
            'api_type' => 'json',
            'kind' => 'self',
            'sr' => $subreddit,
            'title' => $title,
            'text' => $body,
        ];

        $this->applyPublishOptions($params, $options);

        $response = $this->httpClient->post(
            self::API_BASE_URL . '/api/submit',
            [
                'headers' => $this->buildHeaders($token),
                'form_params' => $params,
            ]
        );

        return $this->handleSubmitResponse($response);
    }

    /**
     * Publish a link post (URL submission) to a subreddit.
     */
    private function publishLinkPost(Post $post, string $subreddit, string $token, array $options): PlatformResponseInterface
    {
        $title = $this->formatter->formatTitle($post);
        $url = $options['url'] ?? $post->url;

        if ($url === null || $url === '') {
            throw new PlatformException(
                message: 'Reddit link posts require a URL. Provide it via Post::url or options[\'url\'].',
                platformName: 'reddit',
                httpStatusCode: 0,
            );
        }

        $params = [
            'api_type' => 'json',
            'kind' => 'link',
            'sr' => $subreddit,
            'title' => $title,
            'url' => $url,
        ];

        $this->applyPublishOptions($params, $options);

        $response = $this->httpClient->post(
            self::API_BASE_URL . '/api/submit',
            [
                'headers' => $this->buildHeaders($token),
                'form_params' => $params,
            ]
        );

        return $this->handleSubmitResponse($response);
    }

    /**
     * Parse the Reddit /api/submit response.
     *
     * @throws RateLimitException On rate limit errors.
     * @throws PlatformException  On other API errors.
     */
    private function handleSubmitResponse(array $response): PlatformResponseInterface
    {
        $this->handleErrorResponse($response);

        $data = json_decode($response['body'], true);
        $jsonData = $data['json'] ?? $data;

        // Check for API-level errors in the JSON response
        $errors = $jsonData['errors'] ?? [];
        if (!empty($errors)) {
            $errorMessages = array_map(
                fn(array $err): string => implode(': ', $err),
                $errors,
            );

            throw new PlatformException(
                message: 'Reddit API error: ' . implode('; ', $errorMessages),
                platformName: 'reddit',
                httpStatusCode: $response['status'],
                rawResponse: $data,
            );
        }

        $resultData = $jsonData['data'] ?? [];
        $thingId = $resultData['name'] ?? null; // e.g. t3_abc123
        $permalink = $resultData['url'] ?? null;

        if ($thingId === null) {
            return PlatformResponse::failure(
                'Unexpected response format from Reddit',
                $data ?? [],
            );
        }

        return PlatformResponse::success(
            externalId: $thingId,
            externalUrl: $permalink,
            rawResponse: $data,
        );
    }

    /**
     * Apply optional publish parameters.
     */
    private function applyPublishOptions(array &$params, array $options): void
    {
        // Flair
        if (isset($options['flair_id'])) {
            $params['flair_id'] = $options['flair_id'];
        }
        if (isset($options['flair_text'])) {
            $params['flair_text'] = $options['flair_text'];
        }

        // NSFW flag
        if (isset($options['nsfw'])) {
            $params['nsfw'] = $options['nsfw'] ? 'true' : 'false';
        }

        // Spoiler flag
        if (isset($options['spoiler'])) {
            $params['spoiler'] = $options['spoiler'] ? 'true' : 'false';
        }

        // Send replies to inbox
        if (isset($options['sendreplies'])) {
            $params['sendreplies'] = $options['sendreplies'] ? 'true' : 'false';
        }

        // Resubmit (allow duplicate URL)
        if (isset($options['resubmit'])) {
            $params['resubmit'] = $options['resubmit'] ? 'true' : 'false';
        }
    }

    /**
     * Build the HTTP headers required for Reddit API requests.
     *
     * @return array<string, string>
     */
    private function buildHeaders(string $token): array
    {
        $username = $this->credentials->get('username') ?? 'unknown';

        return [
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => self::USER_AGENT_PREFIX . ' (by /u/' . $username . ')',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

    /**
     * Handle HTTP-level error responses from the Reddit API.
     *
     * @throws RateLimitException On rate limit errors (429 or indicated by headers).
     * @throws PlatformException  On other HTTP errors.
     */
    private function handleErrorResponse(array $response): void
    {
        if ($response['status'] === 429) {
            $retryAfter = null;
            $retrySeconds = $response['headers']['x-ratelimit-reset'] ?? null;

            if ($retrySeconds !== null) {
                $retryAfter = new \DateTimeImmutable('+' . (int) $retrySeconds . ' seconds');
            }

            throw new RateLimitException(
                message: 'Reddit API rate limit exceeded',
                platformName: 'reddit',
                retryAfter: $retryAfter,
                httpStatusCode: 429,
                rawResponse: json_decode($response['body'], true) ?? [],
            );
        }

        if ($response['status'] === 401 || $response['status'] === 403) {
            throw new PlatformException(
                message: 'Reddit authentication failed. Check your access token.',
                platformName: 'reddit',
                httpStatusCode: $response['status'],
                rawResponse: json_decode($response['body'], true) ?? [],
            );
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $data = json_decode($response['body'], true);
            $errorMessage = $data['message'] ?? $data['error'] ?? 'Reddit API error';

            throw new PlatformException(
                message: is_string($errorMessage) ? $errorMessage : 'Reddit API error',
                platformName: 'reddit',
                httpStatusCode: $response['status'],
                rawResponse: $data ?? [],
            );
        }
    }
}
