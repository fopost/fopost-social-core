<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Platforms\Pinterest;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Content\Post;
use Synglify\Core\Exceptions\PlatformException;
use Synglify\Core\Exceptions\RateLimitException;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\Pinterest\PinterestFormatter;
use Synglify\Core\Platforms\Pinterest\PinterestPlatform;

class PinterestPlatformTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private PinterestPlatform $platform;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $credentials = new PlatformCredentials('pinterest', [
            'access_token' => 'test-pinterest-token',
            'board_id' => '12345678901234',
        ]);

        $this->platform = new PinterestPlatform(
            $credentials,
            $this->httpClient,
            new PinterestFormatter(),
        );
    }

    // -------------------------------------------------------------------------
    //  General
    // -------------------------------------------------------------------------

    public function testName(): void
    {
        $this->assertSame('pinterest', $this->platform->name());
    }

    public function testConstraints(): void
    {
        $constraints = $this->platform->constraints();

        $this->assertSame(800, $constraints['max_text_length']);
        $this->assertSame(100, $constraints['max_title_length']);
        $this->assertSame(2_048, $constraints['max_link_length']);
        $this->assertSame(500, $constraints['max_alt_text_length']);
        $this->assertContains('image/jpeg', $constraints['supported_media_types']);
        $this->assertContains('video/mp4', $constraints['supported_media_types']);
    }

    // -------------------------------------------------------------------------
    //  Publish Image Pin
    // -------------------------------------------------------------------------

    public function testPublishImagePin(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/v5/pins'),
                $this->callback(function (array $options) {
                    $json = $options['json'];
                    return $json['board_id'] === '12345678901234'
                        && $json['media_source']['source_type'] === 'image_url'
                        && $json['media_source']['url'] === 'https://example.com/photo.jpg'
                        && str_contains($options['headers']['Authorization'], 'Bearer test-pinterest-token');
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => [],
                'body' => json_encode(['id' => 'pin-123456']),
            ]);

        $post = new Post(title: 'My Pin', body: 'Beautiful image');
        $response = $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
        ]);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('pin-123456', $response->externalId());
        $this->assertStringContainsString('pinterest.com/pin/pin-123456', $response->externalUrl());
    }

    public function testPublishWithTitle(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return $options['json']['title'] === 'My Pin Title';
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => [],
                'body' => json_encode(['id' => 'pin-1']),
            ]);

        $post = new Post(title: 'My Pin Title', body: 'Description');
        $response = $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
        ]);

        $this->assertTrue($response->isSuccess());
    }

    public function testPublishWithLink(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return $options['json']['link'] === 'https://example.com/page';
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => [],
                'body' => json_encode(['id' => 'pin-2']),
            ]);

        $post = new Post(title: '', body: 'Body', url: 'https://example.com/page');
        $response = $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
        ]);

        $this->assertTrue($response->isSuccess());
    }

    public function testPublishWithAltText(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return $options['json']['alt_text'] === 'A sunset over the ocean';
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => [],
                'body' => json_encode(['id' => 'pin-3']),
            ]);

        $post = new Post(title: '', body: 'Sunset');
        $response = $this->platform->publish($post, [
            'image_url' => 'https://example.com/sunset.jpg',
            'alt_text' => 'A sunset over the ocean',
        ]);

        $this->assertTrue($response->isSuccess());
    }

    public function testPublishWithBoardSectionId(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return $options['json']['board_section_id'] === 'section-1';
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => [],
                'body' => json_encode(['id' => 'pin-4']),
            ]);

        $post = new Post(title: '', body: 'Body');
        $response = $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
            'board_section_id' => 'section-1',
        ]);

        $this->assertTrue($response->isSuccess());
    }

    public function testPublishOverridesBoardId(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return $options['json']['board_id'] === 'override-board';
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => [],
                'body' => json_encode(['id' => 'pin-5']),
            ]);

        $post = new Post(title: '', body: 'Body');
        $response = $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
            'board_id' => 'override-board',
        ]);

        $this->assertTrue($response->isSuccess());
    }

    // -------------------------------------------------------------------------
    //  Publish Video Pin
    // -------------------------------------------------------------------------

    public function testPublishVideoPin(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return $options['json']['media_source']['source_type'] === 'video_id'
                        && $options['json']['media_source']['media_id'] === 'uploaded-media-123'
                        && $options['json']['media_source']['cover_image_url'] === 'https://example.com/cover.jpg';
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => [],
                'body' => json_encode(['id' => 'video-pin-1']),
            ]);

        $post = new Post(title: 'Video Pin', body: 'Watch this');
        $response = $this->platform->publish($post, [
            'media_id' => 'uploaded-media-123',
            'cover_image_url' => 'https://example.com/cover.jpg',
        ]);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('video-pin-1', $response->externalId());
    }

    // -------------------------------------------------------------------------
    //  Missing media / board
    // -------------------------------------------------------------------------

    public function testPublishWithoutMediaReturnsFailure(): void
    {
        $post = new Post(title: 'Test', body: 'No media');
        $response = $this->platform->publish($post);

        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('image_url', $response->errorMessage());
    }

    public function testPublishWithoutBoardIdReturnsFailure(): void
    {
        $credentials = new PlatformCredentials('pinterest', [
            'access_token' => 'token',
        ]);

        $platform = new PinterestPlatform($credentials, $this->httpClient);

        $post = new Post(title: 'Test', body: 'No board');
        $response = $platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
        ]);

        $this->assertFalse($response->isSuccess());
        $this->assertStringContainsString('board_id', $response->errorMessage());
    }

    // -------------------------------------------------------------------------
    //  Delete
    // -------------------------------------------------------------------------

    public function testDeleteSuccess(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('delete')
            ->with(
                $this->stringContains('/v5/pins/pin-123'),
                $this->callback(function (array $options) {
                    return str_contains($options['headers']['Authorization'], 'Bearer test-pinterest-token');
                })
            )
            ->willReturn([
                'status' => 204,
                'headers' => [],
                'body' => '',
            ]);

        $this->assertTrue($this->platform->delete('pin-123'));
    }

    public function testDeleteReturnsFalseOnError(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('delete')
            ->willReturn([
                'status' => 404,
                'headers' => [],
                'body' => json_encode(['message' => 'Pin not found']),
            ]);

        $this->assertFalse($this->platform->delete('invalid-pin'));
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
                $this->stringContains('/v5/user_account'),
                $this->callback(function (array $options) {
                    return str_contains($options['headers']['Authorization'], 'Bearer');
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['username' => 'testuser', 'account_type' => 'BUSINESS']),
            ]);

        $this->assertTrue($this->platform->validateCredentials());
    }

    public function testValidateCredentialsReturnsFalseOnError(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn([
                'status' => 401,
                'headers' => [],
                'body' => json_encode(['message' => 'Unauthorized']),
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
                    'code' => 1,
                    'message' => 'Invalid board_id',
                ]),
            ]);

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('Invalid board_id');

        $post = new Post(title: 'Test', body: 'Content');
        $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
        ]);
    }

    public function testPublishThrowsRateLimitExceptionOn429(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 429,
                'headers' => [],
                'body' => json_encode([
                    'message' => 'Rate limit exceeded',
                ]),
            ]);

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $post = new Post(title: 'Test', body: 'Content');
        $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
        ]);
    }

    public function testPublishReturnsFailureWhenNoPinIdReturned(): void
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
        $this->assertStringContainsString('Pin ID', $response->errorMessage());
    }

    // -------------------------------------------------------------------------
    //  Dominant color
    // -------------------------------------------------------------------------

    public function testPublishWithDominantColor(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return $options['json']['dominant_color'] === '#FF5733';
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => [],
                'body' => json_encode(['id' => 'pin-color']),
            ]);

        $post = new Post(title: '', body: 'Colorful');
        $response = $this->platform->publish($post, [
            'image_url' => 'https://example.com/photo.jpg',
            'dominant_color' => '#FF5733',
        ]);

        $this->assertTrue($response->isSuccess());
    }
}
