<?php

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-agnostic library; exceptions are not WordPress output.

namespace Fopost\Social\Platforms\Discord;

use Fopost\Social\Content\Post;
use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Exceptions\PlatformException;
use Fopost\Social\Exceptions\RateLimitException;
use Fopost\Social\Http\Contracts\HttpClientInterface;
use Fopost\Social\Platforms\Contracts\PlatformInterface;
use Fopost\Social\Platforms\Contracts\PlatformResponseInterface;
use Fopost\Social\Platforms\PlatformResponse;

/**
 * Discord REST API / Webhook platform implementation.
 *
 * Publishes content to Discord via two methods:
 * 1. Bot token + channel ID: POST /channels/{id}/messages
 * 2. Webhook URL: POST {webhook_url} (no auth needed)
 *
 * Supports plain text messages, rich embeds, and file attachments.
 * Webhook mode is the simplest for one-way publishing.
 *
 * @see https://discord.com/developers/docs/resources/message
 * @see https://discord.com/developers/docs/resources/webhook
 */
class DiscordPlatform implements PlatformInterface
{
    private const API_BASE_URL = 'https://discord.com/api/v10';
    private const MAX_MESSAGE_LENGTH = 2000;
    private const MAX_EMBED_COUNT = 10;
    private const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25MB (free tier)

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        private readonly DiscordFormatter $formatter,
    ) {
    }

    public function name(): string
    {
        return 'discord';
    }

    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        $useWebhook = $this->credentials->has('webhook_url');

        if ($useWebhook) {
            return $this->publishViaWebhook($post, $options);
        }

        return $this->publishViaBot($post, $options);
    }

    public function delete(string $externalId): bool
    {
        $useWebhook = $this->credentials->has('webhook_url');

        if ($useWebhook) {
            return $this->deleteViaWebhook($externalId);
        }

        return $this->deleteViaBot($externalId);
    }

    public function validateCredentials(): bool
    {
        // Webhook mode: test by sending a dry-run (not possible without posting)
        // So for webhooks, we just validate the URL format
        if ($this->credentials->has('webhook_url')) {
            $url = $this->credentials->require('webhook_url');
            return str_starts_with($url, 'https://discord.com/api/webhooks/')
                || str_starts_with($url, 'https://discordapp.com/api/webhooks/');
        }

        // Bot mode: validate via GET /users/@me
        try {
            $token = $this->credentials->require('bot_token');

            $response = $this->httpClient->get(
                self::API_BASE_URL . '/users/@me',
                [
                    'headers' => $this->buildBotHeaders($token),
                ]
            );

            $data = json_decode($response['body'], true);

            if ($response['status'] !== 200) {
                return false;
            }

            return isset($data['id']);
        } catch (\Throwable) {
            return false;
        }
    }

    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_MESSAGE_LENGTH,
            'max_media_count' => self::MAX_EMBED_COUNT,
            'supported_media_types' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'video/mp4',
                'video/webm',
                'audio/mpeg',
                'audio/ogg',
            ],
            'max_media_size' => self::MAX_FILE_SIZE,
        ];
    }

    /**
     * Publish a message via webhook URL.
     */
    private function publishViaWebhook(Post $post, array $options): PlatformResponseInterface
    {
        $webhookUrl = $this->credentials->require('webhook_url');

        // Append ?wait=true to get the message object back
        $url = $webhookUrl . '?wait=true';

        $payload = $this->buildPayload($post, $options);

        $response = $this->httpClient->post($url, [
            'json' => $payload,
        ]);

        $data = json_decode($response['body'], true);
        $this->handleErrorResponse($response, $data);

        return $this->buildResponse($data);
    }

    /**
     * Publish a message via bot token to a channel.
     */
    private function publishViaBot(Post $post, array $options): PlatformResponseInterface
    {
        $token = $this->credentials->require('bot_token');
        $channelId = $options['channel_id'] ?? $this->credentials->require('channel_id');

        $url = self::API_BASE_URL . '/channels/' . $channelId . '/messages';

        $payload = $this->buildPayload($post, $options);

        $response = $this->httpClient->post($url, [
            'headers' => $this->buildBotHeaders($token),
            'json' => $payload,
        ]);

        $data = json_decode($response['body'], true);
        $this->handleErrorResponse($response, $data);

        return $this->buildResponse($data);
    }

    /**
     * Delete a message via webhook.
     *
     * Webhooks can only delete their own messages using the message ID.
     */
    private function deleteViaWebhook(string $externalId): bool
    {
        $webhookUrl = $this->credentials->require('webhook_url');
        $url = $webhookUrl . '/messages/' . $externalId;

        $response = $this->httpClient->delete($url, []);

        return $response['status'] === 204;
    }

    /**
     * Delete a message via bot token.
     */
    private function deleteViaBot(string $externalId): bool
    {
        $token = $this->credentials->require('bot_token');
        $channelId = $this->credentials->require('channel_id');

        $url = self::API_BASE_URL . '/channels/' . $channelId . '/messages/' . $externalId;

        $response = $this->httpClient->delete($url, [
            'headers' => $this->buildBotHeaders($token),
        ]);

        return $response['status'] === 204;
    }

    /**
     * Build the message payload.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(Post $post, array $options): array
    {
        $useEmbed = $options['embed'] ?? true;
        $payload = [];

        if ($useEmbed) {
            $embed = $this->formatter->formatEmbed($post, $options);
            $payload['embeds'] = [$embed];

            // Optional plain text content alongside embed
            if (isset($options['content'])) {
                $payload['content'] = mb_substr((string) $options['content'], 0, self::MAX_MESSAGE_LENGTH);
            }
        } else {
            $payload['content'] = $this->formatter->format($post);
        }

        // Username override (webhook only)
        if (isset($options['username'])) {
            $payload['username'] = $options['username'];
        }

        // Avatar URL override (webhook only)
        if (isset($options['avatar_url'])) {
            $payload['avatar_url'] = $options['avatar_url'];
        }

        // Thread ID
        if (isset($options['thread_id'])) {
            $payload['thread_id'] = $options['thread_id'];
        }

        // TTS (text-to-speech)
        if (isset($options['tts'])) {
            $payload['tts'] = (bool) $options['tts'];
        }

        return $payload;
    }

    /**
     * Build the HTTP headers for bot token authentication.
     *
     * @return array<string, string>
     */
    private function buildBotHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bot ' . $token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'FoPost (https://fopost.com, 1.0)',
        ];
    }

    /**
     * Build a PlatformResponse from a Discord API response.
     */
    private function buildResponse(array $data): PlatformResponseInterface
    {
        $messageId = $data['id'] ?? null;

        if ($messageId === null) {
            return PlatformResponse::failure('Unexpected response format', $data);
        }

        $channelId = $data['channel_id'] ?? '';
        $guildId = $data['guild_id'] ?? null;

        // Build the message URL
        $externalUrl = null;
        if ($guildId !== null && $channelId !== '') {
            $externalUrl = 'https://discord.com/channels/' . $guildId . '/' . $channelId . '/' . $messageId;
        }

        return PlatformResponse::success(
            externalId: (string) $messageId,
            externalUrl: $externalUrl,
            rawResponse: $data,
        );
    }

    /**
     * Handle error responses from the Discord API.
     *
     * @throws RateLimitException On rate limit errors.
     * @throws PlatformException  On other API errors.
     */
    private function handleErrorResponse(array $response, ?array $data): void
    {
        if ($response['status'] === 429) {
            $retryAfter = null;
            $retryMs = $data['retry_after'] ?? null;

            if ($retryMs !== null) {
                $retryAfter = new \DateTimeImmutable('+' . (int) ceil((float) $retryMs) . ' seconds');
            }

            throw new RateLimitException(
                message: $data['message'] ?? 'Discord API rate limit exceeded',
                platformName: 'discord',
                retryAfter: $retryAfter,
                httpStatusCode: 429,
                rawResponse: $data ?? [],
            );
        }

        if ($response['status'] === 401 || $response['status'] === 403) {
            throw new PlatformException(
                message: $data['message'] ?? 'Discord authentication/authorization failed',
                platformName: 'discord',
                httpStatusCode: $response['status'],
                apiErrorCode: isset($data['code']) ? (string) $data['code'] : null,
                rawResponse: $data ?? [],
            );
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $errorMessage = $data['message'] ?? 'Discord API error';

            throw new PlatformException(
                message: $errorMessage,
                platformName: 'discord',
                httpStatusCode: $response['status'],
                apiErrorCode: isset($data['code']) ? (string) $data['code'] : null,
                rawResponse: $data ?? [],
            );
        }
    }
}
