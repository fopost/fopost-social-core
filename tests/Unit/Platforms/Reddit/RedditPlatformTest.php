<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Platforms\Reddit;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Content\Post;
use Synglify\Core\Exceptions\PlatformException;
use Synglify\Core\Exceptions\RateLimitException;
use Synglify\Core\Formatting\CharacterTruncator;
use Synglify\Core\Formatting\HashtagExtractor;
use Synglify\Core\Http\Contracts\HttpClientInterface;
use Synglify\Core\Platforms\Reddit\RedditFormatter;
use Synglify\Core\Platforms\Reddit\RedditPlatform;

class RedditPlatformTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private RedditPlatform $platform;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $credentials = new PlatformCredentials('reddit', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'access_token' => 'test-access-token',
            'username' => 'testuser',
            'subreddit' => 'test_subreddit',
        ]);

        $formatter = new RedditFormatter(
            new HashtagExtractor(),
            new CharacterTruncator(),
        );

        $this->platform = new RedditPlatform($credentials, $this->httpClient, $formatter);
    }

    public function testName(): void
    {
        $this->assertSame('reddit', $this->platform->name());
    }

    public function testConstraints(): void
    {
        $constraints = $this->platform->constraints();

        $this->assertSame(40000, $constraints['max_text_length']);
        $this->assertSame(300, $constraints['max_title_length']);
        $this->assertSame(1, $constraints['max_media_count']);
    }

    public function testPublishSelfPost(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/submit'),
                $this->callback(function (array $options) {
                    return isset($options['form_params']['kind'])
                        && $options['form_params']['kind'] === 'self'
                        && $options['form_params']['sr'] === 'test_subreddit'
                        && $options['form_params']['title'] === 'My Reddit Post'
                        && isset($options['form_params']['text'])
                        && str_contains($options['headers']['Authorization'], 'Bearer test-access-token')
                        && str_contains($options['headers']['User-Agent'], 'Synglify');
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'json' => [
                        'errors' => [],
                        'data' => [
                            'name' => 't3_abc123',
                            'url' => 'https://www.reddit.com/r/test_subreddit/comments/abc123/my_reddit_post/',
                        ],
                    ],
                ]),
            ]);

        $post = new Post(title: 'My Reddit Post', body: 'This is my post content.');
        $response = $this->platform->publish($post, ['subreddit' => 'test_subreddit']);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('t3_abc123', $response->externalId());
        $this->assertStringContainsString('reddit.com', $response->externalUrl());
    }

    public function testPublishLinkPost(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/submit'),
                $this->callback(function (array $options) {
                    return $options['form_params']['kind'] === 'link'
                        && $options['form_params']['url'] === 'https://example.com/article'
                        && $options['form_params']['sr'] === 'test_subreddit';
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'json' => [
                        'errors' => [],
                        'data' => [
                            'name' => 't3_def456',
                            'url' => 'https://www.reddit.com/r/test_subreddit/comments/def456/check_this/',
                        ],
                    ],
                ]),
            ]);

        // URL post with no body triggers link post
        $post = new Post(title: 'Check This', body: '', url: 'https://example.com/article');
        $response = $this->platform->publish($post, ['subreddit' => 'test_subreddit']);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('t3_def456', $response->externalId());
    }

    public function testPublishExplicitLinkPost(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return $options['form_params']['kind'] === 'link';
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'json' => [
                        'errors' => [],
                        'data' => [
                            'name' => 't3_ghi789',
                            'url' => 'https://reddit.com/r/test/ghi789',
                        ],
                    ],
                ]),
            ]);

        $post = new Post(title: 'Title', body: 'Has body too', url: 'https://example.com');
        $response = $this->platform->publish($post, [
            'subreddit' => 'test_subreddit',
            'kind' => 'link',
        ]);

        $this->assertTrue($response->isSuccess());
    }

    public function testPublishRequiresSubreddit(): void
    {
        // Create platform without subreddit in credentials
        $credentials = new PlatformCredentials('reddit', [
            'client_id' => 'test',
            'client_secret' => 'test',
            'access_token' => 'test',
            'username' => 'testuser',
        ]);

        $formatter = new RedditFormatter(
            new HashtagExtractor(),
            new CharacterTruncator(),
        );

        $platform = new RedditPlatform($credentials, $this->httpClient, $formatter);

        $post = new Post(title: 'No Subreddit', body: 'Content');

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('subreddit');

        $platform->publish($post);
    }

    public function testPublishWithOptions(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return isset($options['form_params']['flair_id'])
                        && $options['form_params']['flair_id'] === 'flair123'
                        && $options['form_params']['nsfw'] === 'true'
                        && $options['form_params']['spoiler'] === 'false';
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'json' => [
                        'errors' => [],
                        'data' => ['name' => 't3_opt1', 'url' => 'https://reddit.com/opt1'],
                    ],
                ]),
            ]);

        $post = new Post(title: 'With Options', body: 'Content');
        $this->platform->publish($post, [
            'subreddit' => 'test_subreddit',
            'flair_id' => 'flair123',
            'nsfw' => true,
            'spoiler' => false,
        ]);
    }

    public function testPublishThrowsOnApiErrors(): void
    {
        $this->httpClient
            ->method('post')
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'json' => [
                        'errors' => [
                            ['SUBREDDIT_NOEXIST', 'Subreddit does not exist', 'sr'],
                        ],
                        'data' => [],
                    ],
                ]),
            ]);

        $post = new Post(title: 'Error Post', body: 'Content');

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('SUBREDDIT_NOEXIST');

        $this->platform->publish($post, ['subreddit' => 'nonexistent_sub']);
    }

    public function testPublishThrowsRateLimitException(): void
    {
        $this->httpClient
            ->method('post')
            ->willReturn([
                'status' => 429,
                'headers' => ['x-ratelimit-reset' => '60'],
                'body' => json_encode(['message' => 'Too Many Requests']),
            ]);

        $post = new Post(title: 'Rate Limited', body: 'Content');

        $this->expectException(RateLimitException::class);

        $this->platform->publish($post, ['subreddit' => 'test_subreddit']);
    }

    public function testPublishThrowsOnAuthError(): void
    {
        $this->httpClient
            ->method('post')
            ->willReturn([
                'status' => 401,
                'headers' => [],
                'body' => json_encode(['error' => 'invalid_token']),
            ]);

        $post = new Post(title: 'Auth Fail', body: 'Content');

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('authentication');

        $this->platform->publish($post, ['subreddit' => 'test_subreddit']);
    }

    public function testDeletePost(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/del'),
                $this->callback(function (array $options) {
                    return $options['form_params']['id'] === 't3_abc123';
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => '{}',
            ]);

        $this->assertTrue($this->platform->delete('t3_abc123'));
    }

    public function testDeleteReturnsFalseOnError(): void
    {
        $this->httpClient
            ->method('post')
            ->willReturn([
                'status' => 403,
                'headers' => [],
                'body' => json_encode(['error' => 'forbidden']),
            ]);

        $this->assertFalse($this->platform->delete('t3_invalid'));
    }

    public function testValidateCredentials(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->with(
                $this->stringContains('/api/v1/me'),
                $this->callback(function (array $options) {
                    return str_contains($options['headers']['Authorization'], 'Bearer test-access-token')
                        && str_contains($options['headers']['User-Agent'], 'testuser');
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['name' => 'testuser', 'id' => 't2_12345']),
            ]);

        $this->assertTrue($this->platform->validateCredentials());
    }

    public function testValidateCredentialsReturnsFalseOnError(): void
    {
        $this->httpClient
            ->method('get')
            ->willReturn([
                'status' => 401,
                'headers' => [],
                'body' => json_encode(['error' => 'invalid_token']),
            ]);

        $this->assertFalse($this->platform->validateCredentials());
    }
}
