<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Telegram;

use Synglify\Core\Content\Media;
use Synglify\Core\Content\Post;
use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Exceptions\PlatformException;
use Synglify\Core\Exceptions\RateLimitException;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\Contracts\PlatformInterface;
use Synglify\Core\Platforms\Contracts\PlatformResponseInterface;
use Synglify\Core\Platforms\PlatformResponse;

/**
 * Telegram Bot API platform implementation.
 */
class TelegramPlatform implements PlatformInterface
{
    private const API_BASE_URL = 'https://api.telegram.org/bot';
    private const MAX_TEXT_LENGTH = 4096;
    private const MAX_CAPTION_LENGTH = 1024;
    private const MAX_MEDIA_GROUP_SIZE = 10;

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        private readonly TelegramFormatter $formatter,
    ) {
    }

    public function name(): string
    {
        return 'telegram';
    }

    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        $chatId = $options['chat_id'] ?? $this->credentials->get('channel_username');

        if ($chatId === null || $chatId === '') {
            throw new PlatformException(
                message: 'Telegram chat_id is required: provide it via options or configure channel_username in credentials.',
                platformName: 'telegram',
            );
        }

        $parseMode = $options['parse_mode'] ?? 'HTML';

        if ($post->hasMedia() && $post->media->count() > 1) {
            return $this->publishMediaGroup($post, $chatId, $parseMode);
        }

        if ($post->hasMedia()) {
            return $this->publishWithMedia($post, $chatId, $parseMode, $options);
        }

        return $this->publishTextMessage($post, $chatId, $parseMode, $options);
    }

    public function delete(string $externalId): bool
    {
        $chatId = $this->credentials->get('channel_username');

        $response = $this->apiRequest('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => (int) $externalId,
        ]);

        return ($response['ok'] ?? false) === true;
    }

    public function validateCredentials(): bool
    {
        try {
            $response = $this->apiRequest('getMe');
            return ($response['ok'] ?? false) === true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_TEXT_LENGTH,
            'max_caption_length' => self::MAX_CAPTION_LENGTH,
            'max_media_count' => self::MAX_MEDIA_GROUP_SIZE,
            'supported_media_types' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'video/mp4',
                'audio/mpeg',
                'audio/ogg',
            ],
            'max_media_size' => 50 * 1024 * 1024, // 50MB
        ];
    }

    /**
     * Publish a text-only message.
     */
    private function publishTextMessage(Post $post, string $chatId, string $parseMode, array $options): PlatformResponseInterface
    {
        $text = $this->formatter->format($post, ['is_caption' => false]);

        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];

        if (!empty($options['disable_web_page_preview'])) {
            $params['disable_web_page_preview'] = true;
        }

        if (!empty($options['disable_notification'])) {
            $params['disable_notification'] = true;
        }

        if (!empty($options['inline_keyboard'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $options['inline_keyboard'],
            ]);
        }

        $response = $this->apiRequest('sendMessage', $params);

        return $this->buildResponse($response);
    }

    /**
     * Publish a message with a single media attachment.
     */
    private function publishWithMedia(Post $post, string $chatId, string $parseMode, array $options): PlatformResponseInterface
    {
        $media = $post->media->first();
        $caption = $this->formatter->format($post, ['is_caption' => true]);

        $method = $this->resolveMediaMethod($media);

        $params = [
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => $parseMode,
        ];

        if (!empty($options['disable_notification'])) {
            $params['disable_notification'] = true;
        }

        if (!empty($options['inline_keyboard'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $options['inline_keyboard'],
            ]);
        }

        // Add media-specific parameters
        $mediaFieldName = $this->resolveMediaFieldName($media);
        $params[$mediaFieldName] = $media->path;

        if ($media->isVideo() || $media->isAudio()) {
            if ($media->duration !== null) {
                $params['duration'] = $media->duration;
            }
        }

        if ($media->isVideo()) {
            if ($media->width !== null) {
                $params['width'] = $media->width;
            }
            if ($media->height !== null) {
                $params['height'] = $media->height;
            }
        }

        $response = $this->apiRequest($method, $params);

        return $this->buildResponse($response);
    }

    /**
     * Publish a media group (2-10 items).
     */
    private function publishMediaGroup(Post $post, string $chatId, string $parseMode): PlatformResponseInterface
    {
        $caption = $this->formatter->format($post, ['is_caption' => true]);
        $mediaItems = [];

        foreach ($post->media->all() as $index => $media) {
            $item = [
                'type' => $media->isVideo() ? 'video' : 'photo',
                'media' => $media->path,
            ];

            // Only the first item gets the caption
            if ($index === 0) {
                $item['caption'] = $caption;
                $item['parse_mode'] = $parseMode;
            }

            $mediaItems[] = $item;
        }

        $params = [
            'chat_id' => $chatId,
            'media' => json_encode($mediaItems),
        ];

        $response = $this->apiRequest('sendMediaGroup', $params);

        return $this->buildResponse($response);
    }

    /**
     * Determine which Telegram Bot API method to use for a media type.
     */
    private function resolveMediaMethod(Media $media): string
    {
        if ($media->isImage()) {
            return 'sendPhoto';
        }
        if ($media->isVideo()) {
            return 'sendVideo';
        }
        if ($media->isAudio()) {
            return 'sendAudio';
        }

        return 'sendDocument';
    }

    /**
     * Get the API parameter field name for the media type.
     */
    private function resolveMediaFieldName(Media $media): string
    {
        if ($media->isImage()) {
            return 'photo';
        }
        if ($media->isVideo()) {
            return 'video';
        }
        if ($media->isAudio()) {
            return 'audio';
        }

        return 'document';
    }

    /**
     * Send a request to the Telegram Bot API.
     *
     * @param string $method Telegram API method name (e.g. 'sendMessage').
     * @param array  $params Request parameters.
     * @return array Decoded API response.
     * @throws PlatformException On API errors.
     * @throws RateLimitException On 429 rate limit responses.
     */
    private function apiRequest(string $method, array $params = []): array
    {
        $apiToken = $this->credentials->require('api_token');
        $url = self::API_BASE_URL . $apiToken . '/' . $method;

        $response = $this->httpClient->post($url, [
            'form_params' => $params,
        ]);

        $data = json_decode($response['body'], true);

        if ($response['status'] === 429) {
            $retryAfter = $data['parameters']['retry_after'] ?? null;
            throw new RateLimitException(
                message: $data['description'] ?? 'Rate limit exceeded',
                platformName: 'telegram',
                retryAfter: $retryAfter !== null
                    ? new \DateTimeImmutable("+{$retryAfter} seconds")
                    : null,
                httpStatusCode: 429,
                rawResponse: $data ?? [],
            );
        }

        if ($response['status'] !== 200 || ($data['ok'] ?? false) !== true) {
            throw new PlatformException(
                message: $data['description'] ?? 'Telegram API error',
                platformName: 'telegram',
                httpStatusCode: $response['status'],
                apiErrorCode: isset($data['error_code']) ? (string) $data['error_code'] : null,
                rawResponse: $data ?? [],
            );
        }

        return $data;
    }

    // ── Extended Telegram Bot API methods ──────────────────────────────────

    /**
     * Send a location to a chat.
     *
     * @param string|int $chatId Chat or channel identifier.
     * @param float $latitude Latitude of the location.
     * @param float $longitude Longitude of the location.
     * @param array $options Optional: live_period, disable_notification, reply_to_message_id, inline_keyboard, reply_keyboard.
     * @return array Raw API response.
     */
    public function sendLocation(string|int $chatId, float $latitude, float $longitude, array $options = []): array
    {
        $params = [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        if (!empty($options['live_period'])) {
            $params['live_period'] = $options['live_period'];
        }

        if (!empty($options['disable_notification'])) {
            $params['disable_notification'] = true;
        }

        if (!empty($options['reply_to_message_id'])) {
            $params['reply_to_message_id'] = $options['reply_to_message_id'];
        }

        if (!empty($options['inline_keyboard'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $options['inline_keyboard'],
            ]);
        }

        if (!empty($options['reply_keyboard'])) {
            $params['reply_markup'] = json_encode([
                'keyboard' => $options['reply_keyboard'],
            ]);
        }

        return $this->apiRequest('sendLocation', $params);
    }

    /**
     * Send a venue to a chat.
     *
     * @param string|int $chatId Chat or channel identifier.
     * @param float $latitude Latitude of the venue.
     * @param float $longitude Longitude of the venue.
     * @param string $title Name of the venue.
     * @param string $address Address of the venue.
     * @param array $options Optional: foursquare_id, disable_notification, reply_to_message_id, inline_keyboard, reply_keyboard.
     * @return array Raw API response.
     */
    public function sendVenue(string|int $chatId, float $latitude, float $longitude, string $title, string $address, array $options = []): array
    {
        $params = [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address,
        ];

        if (!empty($options['foursquare_id'])) {
            $params['foursquare_id'] = $options['foursquare_id'];
        }

        if (!empty($options['disable_notification'])) {
            $params['disable_notification'] = true;
        }

        if (!empty($options['reply_to_message_id'])) {
            $params['reply_to_message_id'] = $options['reply_to_message_id'];
        }

        if (!empty($options['inline_keyboard'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $options['inline_keyboard'],
            ]);
        }

        if (!empty($options['reply_keyboard'])) {
            $params['reply_markup'] = json_encode([
                'keyboard' => $options['reply_keyboard'],
            ]);
        }

        return $this->apiRequest('sendVenue', $params);
    }

    /**
     * Send a phone contact to a chat.
     *
     * @param string|int $chatId Chat or channel identifier.
     * @param string $phoneNumber Contact's phone number.
     * @param string $firstName Contact's first name.
     * @param array $options Optional: last_name, disable_notification, reply_to_message_id, inline_keyboard, reply_keyboard.
     * @return array Raw API response.
     */
    public function sendContact(string|int $chatId, string $phoneNumber, string $firstName, array $options = []): array
    {
        $params = [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
        ];

        if (!empty($options['last_name'])) {
            $params['last_name'] = $options['last_name'];
        }

        if (!empty($options['disable_notification'])) {
            $params['disable_notification'] = true;
        }

        if (!empty($options['reply_to_message_id'])) {
            $params['reply_to_message_id'] = $options['reply_to_message_id'];
        }

        if (!empty($options['inline_keyboard'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $options['inline_keyboard'],
            ]);
        }

        if (!empty($options['reply_keyboard'])) {
            $params['reply_markup'] = json_encode([
                'keyboard' => $options['reply_keyboard'],
            ]);
        }

        return $this->apiRequest('sendContact', $params);
    }

    /**
     * Send a voice message to a chat.
     *
     * @param string|int $chatId Chat or channel identifier.
     * @param string $voicePath Path or URL to the voice file (.ogg encoded with OPUS).
     * @param array $options Optional: caption, parse_mode, duration, disable_notification, reply_to_message_id, inline_keyboard.
     * @return array Raw API response.
     */
    public function sendVoice(string|int $chatId, string $voicePath, array $options = []): array
    {
        $params = [
            'chat_id' => $chatId,
            'voice' => $voicePath,
        ];

        if (!empty($options['caption'])) {
            $params['caption'] = $options['caption'];
        }

        if (!empty($options['parse_mode'])) {
            $params['parse_mode'] = $options['parse_mode'];
        }

        if (!empty($options['duration'])) {
            $params['duration'] = $options['duration'];
        }

        if (!empty($options['disable_notification'])) {
            $params['disable_notification'] = true;
        }

        if (!empty($options['reply_to_message_id'])) {
            $params['reply_to_message_id'] = $options['reply_to_message_id'];
        }

        if (!empty($options['inline_keyboard'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $options['inline_keyboard'],
            ]);
        }

        return $this->apiRequest('sendVoice', $params);
    }

    /**
     * Edit the text of a previously sent message.
     *
     * @param string|int $chatId Chat or channel identifier.
     * @param int $messageId Identifier of the message to edit.
     * @param string $text New text of the message.
     * @param array $options Optional: parse_mode, disable_web_page_preview, inline_keyboard.
     * @return array Raw API response.
     */
    public function editMessageText(string|int $chatId, int $messageId, string $text, array $options = []): array
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
        ];

        if (!empty($options['disable_web_page_preview'])) {
            $params['disable_web_page_preview'] = true;
        }

        if (!empty($options['inline_keyboard'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $options['inline_keyboard'],
            ]);
        }

        return $this->apiRequest('editMessageText', $params);
    }

    /**
     * Edit the caption of a previously sent message.
     *
     * @param string|int $chatId Chat or channel identifier.
     * @param int $messageId Identifier of the message to edit.
     * @param string $caption New caption for the message.
     * @param array $options Optional: parse_mode, inline_keyboard.
     * @return array Raw API response.
     */
    public function editMessageCaption(string|int $chatId, int $messageId, string $caption, array $options = []): array
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $caption,
        ];

        if (!empty($options['parse_mode'])) {
            $params['parse_mode'] = $options['parse_mode'];
        }

        if (!empty($options['inline_keyboard'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $options['inline_keyboard'],
            ]);
        }

        return $this->apiRequest('editMessageCaption', $params);
    }

    /**
     * Pin a message in a chat.
     *
     * @param string|int $chatId Chat or channel identifier.
     * @param int $messageId Identifier of the message to pin.
     * @param array $options Optional: disable_notification.
     * @return bool True on success.
     */
    public function pinMessage(string|int $chatId, int $messageId, array $options = []): bool
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];

        if (!empty($options['disable_notification'])) {
            $params['disable_notification'] = true;
        }

        $response = $this->apiRequest('pinChatMessage', $params);

        return ($response['ok'] ?? false) === true;
    }

    /**
     * Unpin a specific message in a chat.
     *
     * @param string|int $chatId Chat or channel identifier.
     * @param int $messageId Identifier of the message to unpin.
     * @return bool True on success.
     */
    public function unpinMessage(string|int $chatId, int $messageId): bool
    {
        $response = $this->apiRequest('unpinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);

        return ($response['ok'] ?? false) === true;
    }

    /**
     * Unpin all messages in a chat.
     *
     * @param string|int $chatId Chat or channel identifier.
     * @return bool True on success.
     */
    public function unpinAllMessages(string|int $chatId): bool
    {
        $response = $this->apiRequest('unpinAllChatMessages', [
            'chat_id' => $chatId,
        ]);

        return ($response['ok'] ?? false) === true;
    }

    /**
     * Build a PlatformResponse from a successful Telegram API response.
     */
    private function buildResponse(array $data): PlatformResponseInterface
    {
        $result = $data['result'] ?? [];

        // For media groups, result is an array of messages
        if (isset($result[0]['message_id'])) {
            $messageId = (string) $result[0]['message_id'];
        } else {
            $messageId = isset($result['message_id']) ? (string) $result['message_id'] : null;
        }

        if ($messageId === null) {
            return PlatformResponse::failure('Unexpected response format', $data);
        }

        return PlatformResponse::success(
            externalId: $messageId,
            externalUrl: null, // Telegram doesn't return public URLs for channel posts
            rawResponse: $data,
        );
    }
}
