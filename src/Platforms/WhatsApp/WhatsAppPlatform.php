<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\WhatsApp;

use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Content\Post;
use Synglify\Core\Exceptions\PlatformException;
use Synglify\Core\Exceptions\RateLimitException;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\Contracts\PlatformInterface;
use Synglify\Core\Platforms\Contracts\PlatformResponseInterface;
use Synglify\Core\Platforms\PlatformResponse;

/**
 * WhatsApp Cloud API platform implementation.
 *
 * Sends messages via Meta's WhatsApp Cloud API:
 *   POST /{phone_number_id}/messages
 *
 * Supports:
 *   - Text messages (up to 4,096 characters)
 *   - Image messages with caption (public URL)
 *   - Video messages with caption (public URL)
 *   - Document messages with caption (public URL)
 *   - Template messages (pre-approved templates)
 *
 * Required credentials:
 *   - `access_token`     – Bearer token with whatsapp_business_messaging permission
 *   - `phone_number_id`  – WhatsApp Business Phone Number ID
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 */
class WhatsAppPlatform implements PlatformInterface
{
    private const API_BASE_URL = 'https://graph.facebook.com';
    private const DEFAULT_GRAPH_VERSION = 'v19.0';

    /**
     * Maximum text message body length.
     */
    private const MAX_TEXT_LENGTH = 4_096;

    /**
     * Maximum caption length for media messages.
     */
    private const MAX_CAPTION_LENGTH = 1_024;

    private readonly WhatsAppFormatter $formatter;

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        ?WhatsAppFormatter $formatter = null,
        private readonly string $graphVersion = self::DEFAULT_GRAPH_VERSION,
    ) {
        $this->formatter = $formatter ?? new WhatsAppFormatter();
    }

    public function name(): string
    {
        return 'whatsapp';
    }

    /**
     * Send a message via WhatsApp Cloud API.
     *
     * Options:
     *   - `to`              string  Recipient phone number (E.164 format, e.g. "+14155238886"). Required.
     *   - `message_type`    string  'text' (default), 'image', 'video', 'document', or 'template'.
     *   - `image_url`       string  Public URL of the image (for image messages).
     *   - `video_url`       string  Public URL of the video (for video messages).
     *   - `document_url`    string  Public URL of the document (for document messages).
     *   - `filename`        string  Filename for document messages.
     *   - `template_name`   string  Template name (for template messages).
     *   - `template_lang`   string  Template language code (default 'en').
     *   - `template_components` array  Template components array.
     *   - `preview_url`     bool    Enable URL preview in text messages (default true).
     *
     * @throws PlatformException  On API errors or missing recipient.
     * @throws RateLimitException When rate-limited.
     */
    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        $to = $options['to'] ?? null;

        if ($to === null || $to === '') {
            return PlatformResponse::failure(
                'WhatsApp requires a "to" phone number in E.164 format.',
                [],
            );
        }

        $phoneNumberId = $this->credentials->require('phone_number_id');
        $token = $this->credentials->require('access_token');

        $messageType = $options['message_type'] ?? 'text';

        $body = match ($messageType) {
            'image' => $this->buildImageMessage($post, $to, $options),
            'video' => $this->buildVideoMessage($post, $to, $options),
            'document' => $this->buildDocumentMessage($post, $to, $options),
            'template' => $this->buildTemplateMessage($to, $options),
            default => $this->buildTextMessage($post, $to, $options),
        };

        $response = $this->httpClient->post(
            $this->graphApiUrl($phoneNumberId . '/messages'),
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

        $messageId = $data['messages'][0]['id'] ?? null;

        if ($messageId === null) {
            return PlatformResponse::failure('WhatsApp did not return a message ID.', $data);
        }

        return PlatformResponse::success(
            externalId: $messageId,
            externalUrl: '', // WhatsApp messages don't have public URLs
            rawResponse: $data,
        );
    }

    /**
     * Delete is not supported by WhatsApp Cloud API.
     *
     * @throws PlatformException Always.
     */
    public function delete(string $externalId): bool
    {
        throw new PlatformException(
            'WhatsApp does not support deleting sent messages via the API.',
            'whatsapp',
        );
    }

    /**
     * Validate credentials by querying the phone number info.
     */
    public function validateCredentials(): bool
    {
        try {
            $phoneNumberId = $this->credentials->require('phone_number_id');
            $token = $this->credentials->require('access_token');

            $response = $this->httpClient->get(
                $this->graphApiUrl($phoneNumberId),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                    ],
                    'query' => [
                        'fields' => 'id,display_phone_number,verified_name',
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
     *     max_caption_length: int,
     *     supported_media_types: string[],
     *     max_media_size: int,
     * }
     */
    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_TEXT_LENGTH,
            'max_caption_length' => self::MAX_CAPTION_LENGTH,
            'supported_media_types' => [
                'image/jpeg', 'image/png',
                'video/mp4', 'video/3gpp',
                'audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/ogg',
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'max_media_size' => 100 * 1024 * 1024, // 100 MB for video
        ];
    }

    // -------------------------------------------------------------------------
    //  Message Builders
    // -------------------------------------------------------------------------

    private function buildTextMessage(Post $post, string $to, array $options): array
    {
        $text = $this->formatter->format($post, $options);
        $previewUrl = $options['preview_url'] ?? true;

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => $previewUrl,
                'body' => $text,
            ],
        ];
    }

    private function buildImageMessage(Post $post, string $to, array $options): array
    {
        $imageUrl = $options['image_url'] ?? '';

        if ($imageUrl === '') {
            return $this->buildTextMessage($post, $to, $options);
        }

        $image = [
            'link' => $imageUrl,
        ];

        $caption = $this->formatter->formatCaption($post, $options);
        if ($caption !== '') {
            $image['caption'] = $caption;
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'image',
            'image' => $image,
        ];
    }

    private function buildVideoMessage(Post $post, string $to, array $options): array
    {
        $videoUrl = $options['video_url'] ?? '';

        if ($videoUrl === '') {
            return $this->buildTextMessage($post, $to, $options);
        }

        $video = [
            'link' => $videoUrl,
        ];

        $caption = $this->formatter->formatCaption($post, $options);
        if ($caption !== '') {
            $video['caption'] = $caption;
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'video',
            'video' => $video,
        ];
    }

    private function buildDocumentMessage(Post $post, string $to, array $options): array
    {
        $documentUrl = $options['document_url'] ?? '';

        if ($documentUrl === '') {
            return $this->buildTextMessage($post, $to, $options);
        }

        $document = [
            'link' => $documentUrl,
        ];

        if (isset($options['filename'])) {
            $document['filename'] = $options['filename'];
        }

        $caption = $this->formatter->formatCaption($post, $options);
        if ($caption !== '') {
            $document['caption'] = $caption;
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'document',
            'document' => $document,
        ];
    }

    private function buildTemplateMessage(string $to, array $options): array
    {
        $templateName = $options['template_name'] ?? '';
        $langCode = $options['template_lang'] ?? 'en';

        $template = [
            'name' => $templateName,
            'language' => [
                'code' => $langCode,
            ],
        ];

        if (isset($options['template_components']) && is_array($options['template_components'])) {
            $template['components'] = $options['template_components'];
        }

        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => $template,
        ];
    }

    // -------------------------------------------------------------------------
    //  Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a Graph API URL.
     */
    private function graphApiUrl(string $path): string
    {
        return self::API_BASE_URL . '/' . $this->graphVersion . '/' . ltrim($path, '/');
    }

    /**
     * Handle error responses from the WhatsApp Cloud API.
     *
     * @throws RateLimitException On rate limit errors (HTTP 429 or error code 130429).
     * @throws PlatformException  On other API errors.
     */
    private function handleErrorResponse(array $response, ?array $data): void
    {
        if ($response['status'] >= 200 && $response['status'] < 300) {
            return;
        }

        $errorMessage = $data['error']['message'] ?? 'WhatsApp API error';
        $errorCode = $data['error']['code'] ?? null;

        // WhatsApp rate limit: HTTP 429 or error code 130429
        if ($response['status'] === 429 || $errorCode === 130429) {
            throw new RateLimitException(
                message: $errorMessage,
                platformName: 'whatsapp',
                retryAfter: null,
                httpStatusCode: $response['status'],
                rawResponse: $data ?? [],
            );
        }

        throw new PlatformException(
            message: $errorMessage,
            platformName: 'whatsapp',
            httpStatusCode: $response['status'],
            apiErrorCode: $errorCode !== null ? (string) $errorCode : null,
            rawResponse: $data ?? [],
        );
    }
}
