<?php
// phpcs:disable WordPress.WP.AlternativeFunctions -- Test files for framework-agnostic library.

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Platforms\Twitter;

use PHPUnit\Framework\TestCase;
use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Media;
use Fopost\Social\Content\MediaCollection;
use Fopost\Social\Content\Post;
use Fopost\Social\Exceptions\PlatformException;
use Fopost\Social\Exceptions\RateLimitException;
use Fopost\Social\Formatting\CharacterTruncator;
use Fopost\Social\Formatting\HashtagExtractor;
use Fopost\Social\Http\Contracts\HttpClientInterface;
use Fopost\Social\Platforms\Twitter\TwitterFormatter;
use Fopost\Social\Platforms\Twitter\TwitterPlatform;

class TwitterPlatformTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private TwitterPlatform $platform;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $credentials = new PlatformCredentials('twitter', [
            'consumer_key' => 'test-ck',
            'consumer_secret' => 'test-cs',
            'access_token' => 'test-at',
            'access_token_secret' => 'test-ats',
        ]);

        $formatter = new TwitterFormatter(
            new HashtagExtractor(),
            new CharacterTruncator(),
        );

        $this->platform = new TwitterPlatform($credentials, $this->httpClient, $formatter);
    }

    public function testName(): void
    {
        $this->assertSame('twitter', $this->platform->name());
    }

    public function testPublishTweet(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->equalTo('https://api.x.com/2/tweets'),
                $this->callback(function (array $options) {
                    return isset($options['headers']['Authorization'])
                        && str_starts_with($options['headers']['Authorization'], 'OAuth ')
                        && isset($options['json']['text']);
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => [],
                'body' => json_encode([
                    'data' => [
                        'id' => '1234567890',
                        'text' => 'Hello World',
                    ],
                ]),
            ]);

        $post = new Post(title: 'Hello', body: 'Hello World');
        $response = $this->platform->publish($post);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('1234567890', $response->externalId());
        $this->assertSame('https://x.com/i/status/1234567890', $response->externalUrl());
    }

    public function testPublishWithReplyOption(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return isset($options['json']['reply']['in_reply_to_tweet_id'])
                        && $options['json']['reply']['in_reply_to_tweet_id'] === '999';
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => [],
                'body' => json_encode(['data' => ['id' => '1001', 'text' => 'Reply']]),
            ]);

        $post = new Post(title: '', body: 'Reply text');
        $response = $this->platform->publish($post, ['reply_to' => '999']);

        $this->assertTrue($response->isSuccess());
    }

    public function testDeleteTweet(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('delete')
            ->with(
                $this->equalTo('https://api.x.com/2/tweets/123456'),
                $this->callback(function (array $options) {
                    return isset($options['headers']['Authorization']);
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['data' => ['deleted' => true]]),
            ]);

        $this->assertTrue($this->platform->delete('123456'));
    }

    public function testValidateCredentials(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo('https://api.x.com/2/users/me'))
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'data' => ['id' => '12345', 'name' => 'Test User'],
                ]),
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
                'body' => json_encode(['errors' => [['message' => 'Unauthorized']]]),
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
                    'detail' => 'You are not permitted to perform this action',
                    'title' => 'Forbidden',
                ]),
            ]);

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('You are not permitted to perform this action');

        $post = new Post(title: 'Test', body: 'Content');
        $this->platform->publish($post);
    }

    public function testPublishThrowsRateLimitExceptionOn429(): void
    {
        $this->httpClient
            ->expects($this->atLeast(1))
            ->method('post')
            ->willReturn([
                'status' => 429,
                'headers' => [
                    'x-rate-limit-reset' => [(string) (time() + 60)],
                ],
                'body' => json_encode([
                    'title' => 'Too Many Requests',
                    'detail' => 'Rate limit exceeded',
                ]),
            ]);

        $this->expectException(RateLimitException::class);

        $post = new Post(title: 'Test', body: 'Content');
        $this->platform->publish($post);
    }

    public function testOAuthHeaderContainsRequiredFields(): void
    {
        $capturedOptions = null;

        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) use (&$capturedOptions) {
                    $capturedOptions = $options;
                    return true;
                })
            )
            ->willReturn([
                'status' => 201,
                'headers' => [],
                'body' => json_encode(['data' => ['id' => '1', 'text' => 'Test']]),
            ]);

        $post = new Post(title: '', body: 'Test');
        $this->platform->publish($post);

        $authHeader = $capturedOptions['headers']['Authorization'];
        $this->assertStringStartsWith('OAuth ', $authHeader);
        $this->assertStringContainsString('oauth_consumer_key=', $authHeader);
        $this->assertStringContainsString('oauth_nonce=', $authHeader);
        $this->assertStringContainsString('oauth_signature=', $authHeader);
        $this->assertStringContainsString('oauth_signature_method=', $authHeader);
        $this->assertStringContainsString('oauth_timestamp=', $authHeader);
        $this->assertStringContainsString('oauth_token=', $authHeader);
        $this->assertStringContainsString('oauth_version=', $authHeader);
    }

    public function testConstraints(): void
    {
        $constraints = $this->platform->constraints();

        $this->assertSame(280, $constraints['max_text_length']);
        $this->assertSame(4, $constraints['max_media_count']);
        $this->assertContains('image/jpeg', $constraints['supported_media_types']);
        $this->assertContains('video/mp4', $constraints['supported_media_types']);
    }
}
