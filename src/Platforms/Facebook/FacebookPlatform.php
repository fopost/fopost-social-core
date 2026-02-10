<?php

declare(strict_types=1);

namespace Synglify\Core\Platforms\Facebook;

use Synglify\Core\Content\Post;
use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\Contracts\PlatformInterface;
use Synglify\Core\Platforms\Contracts\PlatformResponseInterface;
use Synglify\Core\Platforms\PlatformResponse;

/**
 * Facebook Graph API platform implementation.
 */
class FacebookPlatform implements PlatformInterface
{
    private const API_BASE_URL = 'https://graph.facebook.com';
    private const DEFAULT_GRAPH_VERSION = 'v19.0';
    private const MAX_POST_LENGTH = 63206;

    public function __construct(
        private readonly PlatformCredentials $credentials,
        private readonly HttpClientInterface $httpClient,
        private readonly FacebookFormatter $formatter,
        private readonly string $graphVersion = self::DEFAULT_GRAPH_VERSION,
    ) {
    }

    public function name(): string
    {
        return 'facebook';
    }

    public function publish(Post $post, array $options = []): PlatformResponseInterface
    {
        // TODO: implement — format content, determine type (link/photo/video), call Graph API
        return PlatformResponse::failure('Not implemented yet');
    }

    public function delete(string $externalId): bool
    {
        // TODO: implement — call DELETE /{post-id} on Graph API
        return false;
    }

    public function validateCredentials(): bool
    {
        // TODO: implement — verify page access token via debug_token endpoint
        return false;
    }

    public function constraints(): array
    {
        return [
            'max_text_length' => self::MAX_POST_LENGTH,
            'max_media_count' => 1,
            'supported_media_types' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/bmp',
                'image/tiff',
                'video/mp4',
                'video/avi',
                'video/quicktime',
            ],
            'max_media_size' => 1024 * 1024 * 1024, // 1GB for video
        ];
    }
}
