<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Telegram;

use Synglify\Core\Content\Post;
use Synglify\Core\Config\PlatformCredentials;
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
        // TODO: implement — format content, determine method (sendMessage/sendPhoto/etc.), call API
        return PlatformResponse::failure('Not implemented yet');
    }

    public function delete(string $externalId): bool
    {
        // TODO: implement — call deleteMessage API
        return false;
    }

    public function validateCredentials(): bool
    {
        // TODO: implement — call getMe API to validate bot token
        return false;
    }

    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_TEXT_LENGTH,
            'max_caption_length' => self::MAX_CAPTION_LENGTH,
            'max_media_count' => 10,
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
}
