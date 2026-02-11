<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Platforms\LinkedIn;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Content\Media;
use Synglify\Core\Content\MediaCollection;
use Synglify\Core\Content\Post;
use Synglify\Core\Exceptions\PlatformException;
use Synglify\Core\Exceptions\RateLimitException;
use Synglify\Core\Formatting\CharacterTruncator;
use Synglify\Core\Formatting\HashtagExtractor;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\LinkedIn\LinkedInFormatter;
use Synglify\Core\Platforms\LinkedIn\LinkedInPlatform;

class LinkedInPlatformTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LinkedInPlatform $platform;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $credentials = new PlatformCredentials('linkedin', [
            'access_token' => 'test-access-token',
            'person_id' => 'abc123',
        ]);

        $formatter = new LinkedInFormatter(
            new HashtagExtractor(),
            new CharacterTruncator(),
        );

        $this->platform = new LinkedInPlatform($credentials, $this->httpClient, $formatter);
    }

    public function testName(): void
    {
        $this->assertSame('linkedin', $this->platform->name());
    }

    public function testPublishTextPost(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/posts'),
                $this->callback(function (array $options) {
                    $body = $options['json'] ?? [];
                    return $body['author'] === 'urn:li:person:abc123'
                        && isset($body['commentary'])
                        && $body['visibility'] === 'PUBLIC'
                        && $body['lifecycleState'] === 'PUBLISHED'
                        && !isset($body['content']);
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => ['x-restli-id' => ['urn:li:share:123456']],
                'body' => '{}',
            ]);

        $post = new Post(title: 'Hello', body: 'World');
        $response = $this->platform->publish($post);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('urn:li:share:123456', $response->externalId());
    }

    public function testPublishArticlePost(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    $body = $options['json'] ?? [];
                    return isset($body['content']['article'])
                        && $body['content']['article']['source'] === 'https://example.com'
                        && $body['content']['article']['title'] === 'My Article';
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => ['x-restli-id' => ['urn:li:share:789']],
                'body' => '{}',
            ]);

        $post = new Post(title: 'My Article', body: 'Read this!', url: 'https://example.com');
        $response = $this->platform->publish($post);

        $this->assertTrue($response->isSuccess());
    }

    public function testPublishWithVisibilityOption(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return ($options['json']['visibility'] ?? '') === 'CONNECTIONS';
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => ['x-restli-id' => ['urn:li:share:999']],
                'body' => '{}',
            ]);

        $post = new Post(title: 'Test', body: 'Connections only');
        $response = $this->platform->publish($post, ['visibility' => 'CONNECTIONS']);

        $this->assertTrue($response->isSuccess());
    }

    public function testDeletePost(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('delete')
            ->with(
                $this->stringContains('/posts/'),
                $this->callback(function (array $options) {
                    return str_contains($options['headers']['Authorization'] ?? '', 'Bearer test-access-token');
                })
            )
            ->willReturn([
                'status' => 204,
                'headers' => [],
                'body' => '',
            ]);

        $this->assertTrue($this->platform->delete('urn:li:share:123'));
    }

    public function testDeleteReturnsFalseOnError(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('delete')
            ->willReturn([
                'status' => 404,
                'headers' => [],
                'body' => json_encode(['message' => 'Not Found']),
            ]);

        $this->assertFalse($this->platform->delete('urn:li:share:invalid'));
    }

    public function testValidateCredentials(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->with(
                $this->stringContains('/userinfo'),
                $this->callback(function (array $options) {
                    return str_contains($options['headers']['Authorization'] ?? '', 'Bearer test-access-token');
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['sub' => 'abc123', 'name' => 'Test User']),
            ]);

        $this->assertTrue($this->platform->validateCredentials());
    }

    public function testValidateCredentialsReturnsFalseOnInvalid(): void
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

    public function testPublishThrowsOnApiError(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 403,
                'headers' => [],
                'body' => json_encode([
                    'message' => 'Insufficient permissions',
                    'status' => 403,
                ]),
            ]);

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('Insufficient permissions');

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
                    'message' => 'Rate limit exceeded',
                ]),
            ]);

        $this->expectException(RateLimitException::class);

        $post = new Post(title: 'Test', body: 'Content');
        $this->platform->publish($post);
    }

    public function testPublishReturnsFailureOnMissingPostId(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 201,
                'headers' => [],
                'body' => '{}',
            ]);

        $post = new Post(title: 'Test', body: 'No ID returned');
        $response = $this->platform->publish($post);

        $this->assertFalse($response->isSuccess());
    }

    public function testConstraints(): void
    {
        $constraints = $this->platform->constraints();

        $this->assertSame(3000, $constraints['max_text_length']);
        $this->assertSame(1, $constraints['max_media_count']);
        $this->assertContains('image/jpeg', $constraints['supported_media_types']);
        $this->assertContains('image/png', $constraints['supported_media_types']);
        $this->assertSame(8 * 1024 * 1024, $constraints['max_media_size']);
    }

    public function testOrganizationUrnUsedWhenConfigured(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $credentials = new PlatformCredentials('linkedin', [
            'access_token' => 'test-token',
            'organization_id' => '987654',
        ]);

        $formatter = new LinkedInFormatter(
            new HashtagExtractor(),
            new CharacterTruncator(),
        );

        $platform = new LinkedInPlatform($credentials, $httpClient, $formatter);

        $httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return ($options['json']['author'] ?? '') === 'urn:li:organization:987654';
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => ['x-restli-id' => ['urn:li:share:org-post-1']],
                'body' => '{}',
            ]);

        $post = new Post(title: 'Org Post', body: 'Company update');
        $response = $platform->publish($post);

        $this->assertTrue($response->isSuccess());
    }

    public function testExternalUrlFormat(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 201,
                'headers' => ['x-restli-id' => ['urn:li:share:12345']],
                'body' => '{}',
            ]);

        $post = new Post(title: 'Test', body: 'URL test');
        $response = $this->platform->publish($post);

        $this->assertStringContainsString('linkedin.com/feed/update/', $response->externalUrl());
    }

    public function testRequestHeadersIncludeLinkedInVersion(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    $headers = $options['headers'] ?? [];
                    return isset($headers['LinkedIn-Version'])
                        && isset($headers['X-Restli-Protocol-Version'])
                        && $headers['Authorization'] === 'Bearer test-access-token';
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => ['x-restli-id' => ['urn:li:share:hdr']],
                'body' => '{}',
            ]);

        $post = new Post(title: 'Headers', body: 'Test');
        $this->platform->publish($post);
    }
}
