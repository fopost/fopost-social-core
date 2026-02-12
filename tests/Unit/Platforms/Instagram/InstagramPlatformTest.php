<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Platforms\Instagram;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Content\Post;
use Synglify\Core\Exceptions\PlatformException;
use Synglify\Core\Exceptions\RateLimitException;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\Instagram\InstagramFormatter;
use Synglify\Core\Platforms\Instagram\InstagramPlatform;

class InstagramPlatformTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private InstagramPlatform $platform;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $credentials = new PlatformCredentials('instagram', [
            'access_token' => 'test-access-token',
            'instagram_account_id' => '17841400000000',
        ]);

        $this->platform = new InstagramPlatform(
            $credentials,
            $this->httpClient,
            new InstagramFormatter(),
        );
    }

    // -------------------------------------------------------------------------
    //  General
    // -------------------------------------------------------------------------

    public function testName(): void
    {
        $this->assertSame('instagram', $this->platform->name());
    }

    public function testConstraints(): void
    {
        $constraints = $this->platform->constraints();

        $this->assertSame(2_200, $constraints['max_text_length']);
        $this->assertSame(10, $constraints['max_carousel_items']);
        $this->assertSame(100, $constraints['posts_per_24h']);
        $this->assertContains('image/jpeg', $constraints['supported_media_types']);
        $this->assertContains('video/mp4', $constraints['supported_media_types']);
    }

    // -------------------------------------------------------------------------
    //  Single Image
    // -------------------------------------------------------------------------

    public function testPublishSingleImage(): void
    {
        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnCallback(function (string $url, array $options) {
                if (str_contains($url, '/media_publish')) {
                    // Step 2: publish
                    $this->assertSame('container-123', $options['form_params']['creation_id']);
                    return [
                        'status' => 200,
                        'headers' => [],
                        'body' => json_encode(['id' => 'media-456']),
                    ];
                }

                // Step 1: create container
                $this->assertStringContainsString('/media', $url);
                $this->assertSame('https://example.com/photo.jpg', $options['form_params']['image_url']);
                $this->assertSame('test-access-token', $options['form_params']['access_token']);

                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode(['id' => 'container-123']),
                ];
            });

        $post = new Post(title: 'My Photo', body: 'Great view');
        $response = $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
        ]);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('media-456', $response->externalId());
    }

    public function testPublishImageWithoutUrlReturnFailure(): void
    {
        $post = new Post(title: 'Test', body: 'Content');
        $response = $this->platform->publish($post);

        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('image_url', $response->errorMessage());
    }

    // -------------------------------------------------------------------------
    //  Reels
    // -------------------------------------------------------------------------

    public function testPublishReels(): void
    {
        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnCallback(function (string $url, array $options) {
                if (str_contains($url, '/media_publish')) {
                    return [
                        'status' => 200,
                        'headers' => [],
                        'body' => json_encode(['id' => 'reel-789']),
                    ];
                }

                $this->assertSame('REELS', $options['form_params']['media_type']);
                $this->assertSame('https://example.com/reel.mp4', $options['form_params']['video_url']);

                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode(['id' => 'reel-container-1']),
                ];
            });

        $post = new Post(title: 'My Reel', body: 'Watch this');
        $response = $this->platform->publish($post, [
            'media_type' => 'REELS',
            'video_url' => 'https://example.com/reel.mp4',
        ]);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('reel-789', $response->externalId());
    }

    public function testPublishReelsWithoutVideoUrlReturnFailure(): void
    {
        $post = new Post(title: 'Test', body: 'Content');
        $response = $this->platform->publish($post, [
            'media_type' => 'REELS',
        ]);

        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('video_url', $response->errorMessage());
    }

    public function testPublishReelsWithCoverUrl(): void
    {
        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnCallback(function (string $url, array $options) {
                if (str_contains($url, '/media_publish')) {
                    return [
                        'status' => 200,
                        'headers' => [],
                        'body' => json_encode(['id' => 'reel-X']),
                    ];
                }

                $this->assertSame('https://example.com/cover.jpg', $options['form_params']['cover_url']);
                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode(['id' => 'c-1']),
                ];
            });

        $post = new Post(title: '', body: 'Reel');
        $response = $this->platform->publish($post, [
            'media_type' => 'REELS',
            'video_url' => 'https://example.com/reel.mp4',
            'cover_url' => 'https://example.com/cover.jpg',
        ]);

        $this->assertTrue($response->isSuccess());
    }

    // -------------------------------------------------------------------------
    //  Stories
    // -------------------------------------------------------------------------

    public function testPublishStoryWithImage(): void
    {
        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnCallback(function (string $url, array $options) {
                if (str_contains($url, '/media_publish')) {
                    return [
                        'status' => 200,
                        'headers' => [],
                        'body' => json_encode(['id' => 'story-1']),
                    ];
                }

                $this->assertSame('STORIES', $options['form_params']['media_type']);
                $this->assertSame('https://example.com/story.jpg', $options['form_params']['image_url']);
                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode(['id' => 'sc-1']),
                ];
            });

        $post = new Post(title: '', body: '');
        $response = $this->platform->publish($post, [
            'media_type' => 'STORIES',
            'image_url' => 'https://example.com/story.jpg',
        ]);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('story-1', $response->externalId());
    }

    public function testPublishStoryWithVideo(): void
    {
        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnCallback(function (string $url, array $options) {
                if (str_contains($url, '/media_publish')) {
                    return [
                        'status' => 200,
                        'headers' => [],
                        'body' => json_encode(['id' => 'story-2']),
                    ];
                }

                $this->assertSame('STORIES', $options['form_params']['media_type']);
                $this->assertSame('https://example.com/story.mp4', $options['form_params']['video_url']);
                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode(['id' => 'sc-2']),
                ];
            });

        $post = new Post(title: '', body: '');
        $response = $this->platform->publish($post, [
            'media_type' => 'STORIES',
            'video_url' => 'https://example.com/story.mp4',
        ]);

        $this->assertTrue($response->isSuccess());
    }

    public function testPublishStoryWithoutMediaReturnFailure(): void
    {
        $post = new Post(title: '', body: '');
        $response = $this->platform->publish($post, [
            'media_type' => 'STORIES',
        ]);

        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('image_url or video_url', $response->errorMessage());
    }

    // -------------------------------------------------------------------------
    //  Carousel
    // -------------------------------------------------------------------------

    public function testPublishCarousel(): void
    {
        $callCount = 0;

        $this->httpClient
            ->expects($this->exactly(4)) // 2 child + 1 carousel container + 1 publish
            ->method('post')
            ->willReturnCallback(function (string $url, array $options) use (&$callCount) {
                $callCount++;

                if (str_contains($url, '/media_publish')) {
                    return [
                        'status' => 200,
                        'headers' => [],
                        'body' => json_encode(['id' => 'carousel-published']),
                    ];
                }

                // Carousel container creation (3rd call)
                if ($callCount === 3) {
                    $this->assertSame('CAROUSEL', $options['form_params']['media_type']);
                    $this->assertStringContainsString('child-1', $options['form_params']['children']);
                    $this->assertStringContainsString('child-2', $options['form_params']['children']);
                    return [
                        'status' => 200,
                        'headers' => [],
                        'body' => json_encode(['id' => 'carousel-container']),
                    ];
                }

                // Child container (calls 1 + 2)
                $this->assertSame('true', $options['form_params']['is_carousel_item']);
                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode(['id' => 'child-' . $callCount]),
                ];
            });

        $post = new Post(title: 'Gallery', body: 'Multiple photos');
        $response = $this->platform->publish($post, [
            'carousel' => [
                ['image_url' => 'https://example.com/1.jpg'],
                ['image_url' => 'https://example.com/2.jpg'],
            ],
        ]);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('carousel-published', $response->externalId());
    }

    public function testPublishCarouselWithVideoItem(): void
    {
        $callCount = 0;
        $this->httpClient
            ->expects($this->exactly(3))
            ->method('post')
            ->willReturnCallback(function (string $url, array $options) use (&$callCount) {
                $callCount++;

                if (str_contains($url, '/media_publish')) {
                    return [
                        'status' => 200,
                        'headers' => [],
                        'body' => json_encode(['id' => 'published']),
                    ];
                }

                if ($callCount === 2) {
                    return [
                        'status' => 200,
                        'headers' => [],
                        'body' => json_encode(['id' => 'carousel-c']),
                    ];
                }

                // First child - video
                $this->assertSame('VIDEO', $options['form_params']['media_type']);
                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode(['id' => 'vid-child']),
                ];
            });

        $post = new Post(title: '', body: 'Video post');
        $response = $this->platform->publish($post, [
            'carousel' => [
                ['video_url' => 'https://example.com/vid.mp4'],
            ],
        ]);

        $this->assertTrue($response->isSuccess());
    }

    public function testCarouselLimitedToTenItems(): void
    {
        $items = [];
        for ($i = 0; $i < 15; $i++) {
            $items[] = ['image_url' => "https://example.com/{$i}.jpg"];
        }

        $callCount = 0;
        // 10 children + 1 carousel container + 1 publish = 12 total
        $this->httpClient
            ->expects($this->exactly(12))
            ->method('post')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode(['id' => "id-{$callCount}"]),
                ];
            });

        $post = new Post(title: '', body: 'Many photos');
        $response = $this->platform->publish($post, [
            'carousel' => $items,
        ]);

        $this->assertTrue($response->isSuccess());
    }

    // -------------------------------------------------------------------------
    //  Delete
    // -------------------------------------------------------------------------

    public function testDeleteThrowsException(): void
    {
        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('does not support deleting');

        $this->platform->delete('some-media-id');
    }

    // -------------------------------------------------------------------------
    //  Validate Credentials
    // -------------------------------------------------------------------------

    public function testValidateCredentialsSuccess(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->with(
                $this->stringContains('17841400000000'),
                $this->callback(function (array $options) {
                    return $options['query']['access_token'] === 'test-access-token'
                        && str_contains($options['query']['fields'], 'username');
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['id' => '17841400000000', 'username' => 'testuser']),
            ]);

        $this->assertTrue($this->platform->validateCredentials());
    }

    public function testValidateCredentialsReturnsFalseOnError(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn([
                'status' => 400,
                'headers' => [],
                'body' => json_encode(['error' => ['message' => 'Invalid token']]),
            ]);

        $this->assertFalse($this->platform->validateCredentials());
    }

    public function testValidateCredentialsReturnsFalseOnException(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->assertFalse($this->platform->validateCredentials());
    }

    // -------------------------------------------------------------------------
    //  Error Handling
    // -------------------------------------------------------------------------

    public function testPublishThrowsPlatformExceptionOnApiError(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 400,
                'headers' => [],
                'body' => json_encode([
                    'error' => [
                        'message' => 'Invalid image URL',
                        'code' => 100,
                    ],
                ]),
            ]);

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('Invalid image URL');

        $post = new Post(title: 'Test', body: 'Content');
        $this->platform->publish($post, [
            'image_url' => 'https://invalid.example.com/photo.jpg',
        ]);
    }

    public function testPublishThrowsRateLimitExceptionOnHttp429(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 429,
                'headers' => [],
                'body' => json_encode([
                    'error' => [
                        'message' => 'Too many requests',
                        'code' => 4,
                    ],
                ]),
            ]);

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Too many requests');

        $post = new Post(title: 'Test', body: '');
        $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
        ]);
    }

    public function testPublishThrowsRateLimitExceptionOnPageLevelLimit(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 400,
                'headers' => [],
                'body' => json_encode([
                    'error' => [
                        'message' => 'Application request limit reached',
                        'code' => 32,
                    ],
                ]),
            ]);

        $this->expectException(RateLimitException::class);

        $post = new Post(title: 'Test', body: '');
        $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
        ]);
    }

    // -------------------------------------------------------------------------
    //  Container creation failure
    // -------------------------------------------------------------------------

    public function testPublishReturnsFailureWhenContainerIdMissing(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([]),
            ]);

        $post = new Post(title: 'Test', body: 'Content');
        $response = $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
        ]);

        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('container', $response->errorMessage());
    }

    public function testPublishReturnsFailureWhenPublishIdMissing(): void
    {
        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnCallback(function (string $url) {
                if (str_contains($url, '/media_publish')) {
                    return [
                        'status' => 200,
                        'headers' => [],
                        'body' => json_encode([]),
                    ];
                }

                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode(['id' => 'container-ok']),
                ];
            });

        $post = new Post(title: 'Test', body: 'Content');
        $response = $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
        ]);

        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('publish', $response->errorMessage());
    }

    // -------------------------------------------------------------------------
    //  Optional params
    // -------------------------------------------------------------------------

    public function testPublishWithLocationAndAltText(): void
    {
        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnCallback(function (string $url, array $options) {
                if (str_contains($url, '/media_publish')) {
                    return [
                        'status' => 200,
                        'headers' => [],
                        'body' => json_encode(['id' => 'media-1']),
                    ];
                }

                $this->assertSame('loc-123', $options['form_params']['location_id']);
                $this->assertSame('A beautiful sunset', $options['form_params']['alt_text']);
                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode(['id' => 'c-1']),
                ];
            });

        $post = new Post(title: '', body: 'Sunset');
        $response = $this->platform->publish($post, [
            'image_url' => 'https://example.com/sunset.jpg',
            'location_id' => 'loc-123',
            'alt_text' => 'A beautiful sunset',
        ]);

        $this->assertTrue($response->isSuccess());
    }

    public function testPublishWithUserTags(): void
    {
        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnCallback(function (string $url, array $options) {
                if (str_contains($url, '/media_publish')) {
                    return [
                        'status' => 200,
                        'headers' => [],
                        'body' => json_encode(['id' => 'media-1']),
                    ];
                }

                $tags = json_decode($options['form_params']['user_tags'], true);
                $this->assertCount(1, $tags);
                $this->assertSame('testuser', $tags[0]['username']);
                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => json_encode(['id' => 'c-1']),
                ];
            });

        $post = new Post(title: '', body: 'Photo');
        $response = $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
            'user_tags' => [['username' => 'testuser', 'x' => 0.5, 'y' => 0.5]],
        ]);

        $this->assertTrue($response->isSuccess());
    }
}
