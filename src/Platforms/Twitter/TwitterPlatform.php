<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Twitter;

use Synglify\Core\Content\Post;
use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\Contracts\PlatformInterface;
use Synglify\Core\Platforms\Contracts\PlatformResponseInterface;
use Synglify\Core\Platforms\PlatformResponse;

/**
 * X/Twitter API v2 platform implementation.
 */
class TwitterPlatform implements PlatformInterface
{
    private const API_BASE_URL = 'https://api.x.com/2';
    private const UPLOAD_URL = 'https://upload.x.com/1.1/media/upload.json';
    private const MAX_TWEET_LENGTH = 280;
    private const MAX_MEDIA_COUNT = 4;

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        private readonly TwitterFormatter $formatter,
    ) {
    }

    public function name(): string
    {
        return 'twitter';
    }

    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        // TODO: implement — format content, handle media uploads, post tweet via API v2
        return PlatformResponse::failure('Not implemented yet');
    }

    public function delete(string $externalId): bool
    {
        // TODO: implement — call DELETE /2/tweets/:id
        return false;
    }

    public function validateCredentials(): bool
    {
        // TODO: implement — call GET /2/users/me to validate credentials
        return false;
    }

    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_TWEET_LENGTH,
            'max_media_count' => self::MAX_MEDIA_COUNT,
            'supported_media_types' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'video/mp4',
                'video/quicktime',
            ],
            'max_media_size' => 512 * 1024 * 1024, // 512MB for video, 5MB for images
        ];
    }
}
