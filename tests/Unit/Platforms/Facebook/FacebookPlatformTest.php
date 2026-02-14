<?php

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Platforms\Facebook;

use PHPUnit\Framework\TestCase;
use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Content\Media;
use Owlstack\Core\Content\MediaCollection;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Exceptions\PlatformException;
use Owlstack\Core\Exceptions\RateLimitException;
use Owlstack\Core\Formatting\CharacterTruncator;
use Owlstack\Core\Formatting\HashtagExtractor;
use Owlstack\Core\Http\Contracts\HttpClientInterface;
use Owlstack\Core\Platforms\Facebook\FacebookFormatter;
use Owlstack\Core\Platforms\Facebook\FacebookPlatform;

class FacebookPlatformTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private FacebookPlatform $platform;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $credentials = new PlatformCredentials('facebook', [
            'app_id' => 'test-app-id',
            'app_secret' => 'test-app-secret',
            'page_access_token' => 'test-page-token',
            'page_id' => '123456789',
        ]);

        $formatter = new FacebookFormatter(
            new HashtagExtractor(),
            new CharacterTruncator(),
        );

        $this->platform = new FacebookPlatform($credentials, $this->httpClient, $formatter);
    }

    public function testName(): void
    {
        $this->assertSame('facebook', $this->platform->name());
    }

    public function testPublishLinkPost(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('123456789/feed'),
                $this->callback(function (array $options) {
                    return isset($options['form_params']['message'])
                        && isset($options['form_params']['link'])
                        && $options['form_params']['link'] === 'https://example.com'
                        && $options['form_params']['access_token'] === 'test-page-token';
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['id' => '123456789_987654321']),
            ]);

        $post = new Post(title: 'My Link', body: 'Check it out', url: 'https://example.com');
        $response = $this->platform->publish($post);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('123456789_987654321', $response->externalId());
    }

    public function testPublishTextOnlyPost(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/feed'),
                $this->callback(function (array $options) {
                    return isset($options['form_params']['message'])
                        && !isset($options['form_params']['link']);
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['id' => '123_456']),
            ]);

        $post = new Post(title: 'Hello', body: 'World');
        $response = $this->platform->publish($post);

        $this->assertTrue($response->isSuccess());
    }

    public function testPublishWithPrivacyOption(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return isset($options['form_params']['privacy']);
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['id' => '123_789']),
            ]);

        $post = new Post(title: 'Test', body: 'Privacy test');
        $response = $this->platform->publish($post, [
            'privacy' => ['value' => 'EVERYONE'],
        ]);

        $this->assertTrue($response->isSuccess());
    }

    public function testDeletePost(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('delete')
            ->with(
                $this->stringContains('123_456'),
                $this->callback(function (array $options) {
                    return $options['query']['access_token'] === 'test-page-token';
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['success' => true]),
            ]);

        $this->assertTrue($this->platform->delete('123_456'));
    }

    public function testDeleteReturnsFalseOnError(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('delete')
            ->willReturn([
                'status' => 400,
                'headers' => [],
                'body' => json_encode(['error' => ['message' => 'Invalid ID']]),
            ]);

        $this->assertFalse($this->platform->delete('invalid'));
    }

    public function testValidateCredentials(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->with(
                $this->stringContains('debug_token'),
                $this->callback(function (array $options) {
                    return $options['query']['input_token'] === 'test-page-token'
                        && $options['query']['access_token'] === 'test-app-id|test-app-secret';
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'data' => ['is_valid' => true, 'app_id' => 'test-app-id'],
                ]),
            ]);

        $this->assertTrue($this->platform->validateCredentials());
    }

    public function testValidateCredentialsReturnsFalseOnInvalid(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'data' => ['is_valid' => false],
                ]),
            ]);

        $this->assertFalse($this->platform->validateCredentials());
    }

    public function testPublishThrowsOnApiError(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 400,
                'headers' => [],
                'body' => json_encode([
                    'error' => [
                        'message' => 'Invalid access token',
                        'code' => 190,
                    ],
                ]),
            ]);

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('Invalid access token');

        $post = new Post(title: 'Test', body: 'Content');
        $this->platform->publish($post);
    }

    public function testPublishThrowsRateLimitException(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 429,
                'headers' => [],
                'body' => json_encode([
                    'error' => [
                        'message' => 'Too many API calls',
                        'code' => 4,
                    ],
                ]),
            ]);

        $this->expectException(RateLimitException::class);

        $post = new Post(title: 'Test', body: 'Content');
        $this->platform->publish($post);
    }

    public function testConstraints(): void
    {
        $constraints = $this->platform->constraints();

        $this->assertSame(63206, $constraints['max_text_length']);
        $this->assertSame(1, $constraints['max_media_count']);
        $this->assertContains('image/jpeg', $constraints['supported_media_types']);
        $this->assertContains('video/mp4', $constraints['supported_media_types']);
    }
}
