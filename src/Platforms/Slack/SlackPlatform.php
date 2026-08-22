<?php

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-agnostic library; exceptions are not WordPress output.
// phpcs:disable Universal.Operators.DisallowShortTernary.Found -- Short ternary used intentionally for concise null/empty fallbacks.

namespace Fopost\Social\Platforms\Slack;

use DateTimeImmutable;
use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Exceptions\PlatformException;
use Fopost\Social\Exceptions\RateLimitException;
use Fopost\Social\Http\Contracts\HttpClientInterface;
use Fopost\Social\Platforms\Contracts\PlatformInterface;
use Fopost\Social\Platforms\Contracts\PlatformResponseInterface;
use Fopost\Social\Platforms\PlatformResponse;

/**
 * Slack platform integration.
 *
 * Supports two modes:
 *   1. **Bot token** – uses Slack Web API (`chat.postMessage`, `chat.delete`, `auth.test`).
 *   2. **Incoming webhook** – a simpler, fire-and-forget approach for posting.
 *
 * Bot token mode requires `bot_token` and `channel` credentials.
 * Webhook mode requires only `webhook_url`.
 *
 * When both are provided, bot token takes precedence (more features).
 *
 * @see https://docs.slack.dev/reference/methods/chat.postMessage
 * @see https://docs.slack.dev/reference/methods/chat.delete
 * @see https://docs.slack.dev/reference/methods/auth.test
 */
class SlackPlatform implements PlatformInterface
{
    private const API_BASE = 'https://slack.com/api';

    /**
     * Recommended text limit (Slack truncates at 40,000).
     */
    private const MAX_TEXT_LENGTH = 40_000;

    /**
     * Maximum number of attachments per message.
     */
    private const MAX_ATTACHMENTS = 100;

    /**
     * Maximum blocks per message.
     */
    private const MAX_BLOCKS = 50;

    private readonly SlackFormatter $formatter;

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        ?SlackFormatter $formatter = null,
    ) {
        $this->formatter = $formatter ?? new SlackFormatter();
    }

    public function name(): string
    {
        return 'slack';
    }

    /**
     * Publish content to Slack.
     *
     * Options:
     *   - `channel`       string  Override the default channel (bot mode only).
     *   - `blocks`        bool    Use Block Kit formatting (default false).
     *   - `thread_ts`     string  Reply in a thread.
     *   - `unfurl_links`  bool    Unfurl text-based URLs (default true).
     *   - `unfurl_media`  bool    Unfurl media URLs (default true).
     *   - `username`      string  Override the bot's display name.
     *   - `icon_url`      string  Override the bot's icon with an image URL.
     *   - `icon_emoji`    string  Override the bot's icon with an emoji (e.g., `:robot_face:`).
     *   - `reply_broadcast` bool  Also post threaded reply to channel (default false).
     *   - `mrkdwn`        bool    Enable mrkdwn formatting (default true).
     *
     * @throws PlatformException  On API errors.
     * @throws RateLimitException When rate-limited.
     */
    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        if ($this->isWebhookMode()) {
            return $this->publishViaWebhook($post, $options);
        }

        return $this->publishViaBot($post, $options);
    }

    /**
     * Delete a previously published message (bot mode only).
     *
     * The `$externalId` must be in the format `channel:timestamp`
     * (e.g., `C123ABC456:1503435956.000247`).
     *
     * @throws PlatformException  On API errors or when in webhook mode.
     * @throws RateLimitException When rate-limited.
     */
    public function delete(string $externalId): bool
    {
        if ($this->isWebhookMode()) {
            throw new PlatformException(
                'Slack incoming webhooks do not support message deletion. Use bot token mode.',
                'slack',
            );
        }

        [$channel, $timestamp] = $this->parseExternalId($externalId);

        $response = $this->httpClient->post(
            self::API_BASE . '/chat.delete',
            [
                'headers' => $this->botHeaders(),
                'json' => [
                    'channel' => $channel,
                    'ts' => $timestamp,
                ],
            ],
        );

        $this->checkRateLimit($response);

        $data = json_decode($response['body'], true) ?: [];

        if (($data['ok'] ?? false) !== true) {
            throw new PlatformException(
                'Slack delete failed: ' . ($data['error'] ?? 'Unknown error'),
                'slack',
                $response['status'],
                $data['error'] ?? null,
                $data,
            );
        }

        return true;
    }

    /**
     * Validate that the configured credentials are working.
     *
     * Bot mode: calls `auth.test`.
     * Webhook mode: validates the webhook URL format.
     *
     * @throws PlatformException On API errors.
     */
    public function validateCredentials(): bool
    {
        if ($this->isWebhookMode()) {
            $webhookUrl = $this->credentials->get('webhook_url', '');

            return is_string($webhookUrl)
                && str_starts_with($webhookUrl, 'https://hooks.slack.com/');
        }

        $response = $this->httpClient->post(
            self::API_BASE . '/auth.test',
            [
                'headers' => $this->botHeaders(),
                'json' => new \stdClass(), // POST requires a body
            ],
        );

        $this->checkRateLimit($response);

        $data = json_decode($response['body'], true) ?: [];

        return ($data['ok'] ?? false) === true;
    }

    /**
     * Get Slack's content constraints.
     *
     * @return array{
     *     max_text_length: int,
     *     max_media_count: int,
     *     supported_media_types: string[],
     *     max_media_size: int,
     *     max_blocks: int,
     *     max_attachments: int,
     * }
     */
    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_TEXT_LENGTH,
            'max_media_count' => 10,
            'supported_media_types' => ['image/jpeg', 'image/png', 'image/gif'],
            'max_media_size' => 0, // Media sent via files.upload, not chat.postMessage
            'max_blocks' => self::MAX_BLOCKS,
            'max_attachments' => self::MAX_ATTACHMENTS,
        ];
    }

    // -------------------------------------------------------------------------
    //  Bot Token Mode
    // -------------------------------------------------------------------------

    private function publishViaBot(Post $post, array $options): PlatformResponseInterface
    {
        $channel = $options['channel'] ?? $this->credentials->require('channel');
        $useBlocks = $options['blocks'] ?? false;

        $payload = [
            'channel' => $channel,
            'mrkdwn' => $options['mrkdwn'] ?? true,
        ];

        if ($useBlocks) {
            $blocks = $this->formatter->formatBlocks($post, $options);
            $payload['blocks'] = $blocks;
            // Fallback text for notifications / screen readers
            $payload['text'] = $this->buildFallbackText($post);
        } else {
            $payload['text'] = $this->formatter->format($post, $options);
        }

        // Optional parameters
        $this->applyOptionalParams($payload, $options);

        $response = $this->httpClient->post(
            self::API_BASE . '/chat.postMessage',
            [
                'headers' => $this->botHeaders(),
                'json' => $payload,
            ],
        );

        $this->checkRateLimit($response);

        $data = json_decode($response['body'], true) ?: [];

        if (($data['ok'] ?? false) !== true) {
            return PlatformResponse::failure(
                'Slack API error: ' . ($data['error'] ?? 'Unknown error'),
                $data,
            );
        }

        $channel = $data['channel'] ?? '';
        $ts = $data['ts'] ?? '';
        $externalId = $channel . ':' . $ts;

        // Construct the message permalink (workspace URL not available here,
        // so we use the channel:ts identifier as external URL too)
        return PlatformResponse::success(
            externalId: $externalId,
            externalUrl: null,
            rawResponse: $data,
        );
    }

    // -------------------------------------------------------------------------
    //  Webhook Mode
    // -------------------------------------------------------------------------

    private function publishViaWebhook(Post $post, array $options): PlatformResponseInterface
    {
        $webhookUrl = $this->credentials->require('webhook_url');
        $useBlocks = $options['blocks'] ?? false;

        $payload = [];

        if ($useBlocks) {
            $payload['blocks'] = $this->formatter->formatBlocks($post, $options);
            $payload['text'] = $this->buildFallbackText($post);
        } else {
            $payload['text'] = $this->formatter->format($post, $options);
        }

        // Webhook supports username and icon overrides
        if (isset($options['username'])) {
            $payload['username'] = $options['username'];
        }
        if (isset($options['icon_url'])) {
            $payload['icon_url'] = $options['icon_url'];
        }
        if (isset($options['icon_emoji'])) {
            $payload['icon_emoji'] = $options['icon_emoji'];
        }

        $response = $this->httpClient->post(
            $webhookUrl,
            [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $payload,
            ],
        );

        $this->checkRateLimit($response);

        // Webhook returns plain text "ok" on success (not JSON)
        $body = trim($response['body']);

        if ($response['status'] >= 200 && $response['status'] < 300 && $body === 'ok') {
            return PlatformResponse::success(
                externalId: 'webhook_' . time(),
                externalUrl: null,
                rawResponse: ['response' => $body],
            );
        }

        return PlatformResponse::failure(
            'Slack webhook error: ' . $body,
            ['status' => $response['status'], 'body' => $body],
        );
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    /**
     * Determine if we are in webhook mode (no bot_token).
     */
    private function isWebhookMode(): bool
    {
        return !$this->credentials->has('bot_token')
            && $this->credentials->has('webhook_url');
    }

    /**
     * Build HTTP headers for bot token API calls.
     *
     * @return array<string, string>
     */
    private function botHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->credentials->require('bot_token'),
            'Content-Type' => 'application/json; charset=utf-8',
        ];
    }

    /**
     * Build a plain-text fallback for Block Kit messages (used in notifications).
     */
    private function buildFallbackText(Post $post): string
    {
        $text = $post->title !== '' ? $post->title : '';

        if ($post->body !== '' && $text === '') {
            $text = mb_substr($post->body, 0, 200);
        } elseif ($post->body !== '') {
            $text .= ' — ' . mb_substr($post->body, 0, 200);
        }

        return $text !== '' ? $text : 'New message';
    }

    /**
     * Apply optional chat.postMessage parameters to the payload.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function applyOptionalParams(array &$payload, array $options): void
    {
        $optionalKeys = [
            'thread_ts',
            'reply_broadcast',
            'unfurl_links',
            'unfurl_media',
            'username',
            'icon_url',
            'icon_emoji',
        ];

        foreach ($optionalKeys as $key) {
            if (isset($options[$key])) {
                $payload[$key] = $options[$key];
            }
        }
    }

    /**
     * Parse a `channel:timestamp` external ID into its parts.
     *
     * @return array{0: string, 1: string}
     * @throws PlatformException If the format is invalid.
     */
    private function parseExternalId(string $externalId): array
    {
        if (!str_contains($externalId, ':')) {
            throw new PlatformException(
                "Invalid Slack external ID format. Expected 'channel:timestamp', got: {$externalId}",
                'slack',
            );
        }

        $parts = explode(':', $externalId, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new PlatformException(
                "Invalid Slack external ID format. Expected 'channel:timestamp', got: {$externalId}",
                'slack',
            );
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * Check for rate-limit responses and throw if rate-limited.
     *
     * @param array{status: int, headers: array, body: string} $response
     * @throws RateLimitException
     */
    private function checkRateLimit(array $response): void
    {
        if ($response['status'] !== 429) {
            return;
        }

        $retryAfter = null;
        $retrySeconds = $this->extractRetryAfter($response['headers']);

        if ($retrySeconds !== null) {
            $retryAfter = new DateTimeImmutable('+' . $retrySeconds . ' seconds');
        }

        throw new RateLimitException(
            message: 'Slack API rate limit exceeded. Retry after ' . ($retrySeconds ?? '?') . ' seconds.',
            platformName: 'slack',
            retryAfter: $retryAfter,
            httpStatusCode: 429,
            rawResponse: json_decode($response['body'], true) ?: [],
        );
    }

    /**
     * Extract the Retry-After value (in seconds) from response headers.
     */
    private function extractRetryAfter(array $headers): ?int
    {
        // Normalise header keys to lowercase
        $normalised = [];
        foreach ($headers as $key => $value) {
            $normalised[strtolower((string) $key)] = $value;
        }

        $value = $normalised['retry-after'] ?? null;

        if ($value === null) {
            return null;
        }

        $seconds = is_array($value) ? (int) $value[0] : (int) $value;

        return $seconds > 0 ? $seconds : null;
    }
}
