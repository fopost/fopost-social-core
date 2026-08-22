<?php
// phpcs:disable WordPress.WP.AlternativeFunctions -- Test files for framework-agnostic library.

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Platforms\Slack;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Exceptions\PlatformException;
use Fopost\Social\Exceptions\RateLimitException;
use Fopost\Social\Http\Contracts\HttpClientInterface;
use Fopost\Social\Platforms\Slack\SlackPlatform;

class SlackPlatformTest extends TestCase
{
    private function mockHttp(array $response): HttpClientInterface
    {
        $http = $this->createMock(HttpClientInterface::class);

        $http->method('post')->willReturn($response);
        $http->method('get')->willReturn($response);
        $http->method('delete')->willReturn($response);

        return $http;
    }

    private function botCredentials(): PlatformCredentials
    {
        return new PlatformCredentials('slack', [
            'bot_token' => 'xoxb-test-token',
            'channel' => 'C123ABC456',
        ]);
    }

    private function webhookCredentials(): PlatformCredentials
    {
        return new PlatformCredentials('slack', [
            'webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxxx',
        ]);
    }

    // -----------------------------------------------------------------------
    //  Platform Name
    // -----------------------------------------------------------------------

    #[Test]
    public function it_returns_platform_name(): void
    {
        $platform = new SlackPlatform(
            $this->botCredentials(),
            $this->mockHttp(['status' => 200, 'headers' => [], 'body' => '{}']),
        );

        $this->assertSame('slack', $platform->name());
    }

    // -----------------------------------------------------------------------
    //  Bot Token Publish
    // -----------------------------------------------------------------------

    #[Test]
    public function it_publishes_via_bot_token_plain_text(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                'https://slack.com/api/chat.postMessage',
                $this->callback(function (array $options): bool {
                    $this->assertArrayHasKey('headers', $options);
                    $this->assertStringContainsString('Bearer xoxb-test-token', $options['headers']['Authorization']);

                    $this->assertArrayHasKey('json', $options);
                    $this->assertSame('C123ABC456', $options['json']['channel']);
                    $this->assertArrayHasKey('text', $options['json']);
                    $this->assertArrayNotHasKey('blocks', $options['json']);

                    return true;
                }),
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'ok' => true,
                    'channel' => 'C123ABC456',
                    'ts' => '1503435956.000247',
                    'message' => ['text' => 'Hello'],
                ]),
            ]);

        $platform = new SlackPlatform($this->botCredentials(), $http);
        $post = new Post(title: 'Hello', body: 'World');

        $result = $platform->publish($post);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('C123ABC456:1503435956.000247', $result->externalId());
    }

    #[Test]
    public function it_publishes_via_bot_with_blocks(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                'https://slack.com/api/chat.postMessage',
                $this->callback(function (array $options): bool {
                    $this->assertArrayHasKey('blocks', $options['json']);
                    $this->assertArrayHasKey('text', $options['json']); // fallback text

                    return true;
                }),
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'ok' => true,
                    'channel' => 'C123ABC456',
                    'ts' => '1503435956.000248',
                ]),
            ]);

        $platform = new SlackPlatform($this->botCredentials(), $http);
        $post = new Post(title: 'Block Post', body: 'With blocks');

        $result = $platform->publish($post, ['blocks' => true]);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_passes_optional_params_to_api(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                'https://slack.com/api/chat.postMessage',
                $this->callback(function (array $options): bool {
                    $json = $options['json'];
                    $this->assertSame('1503435956.000247', $json['thread_ts']);
                    $this->assertSame('CustomBot', $json['username']);
                    $this->assertSame(':robot_face:', $json['icon_emoji']);
                    $this->assertFalse($json['unfurl_links']);

                    return true;
                }),
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['ok' => true, 'channel' => 'C123', 'ts' => '123.456']),
            ]);

        $platform = new SlackPlatform($this->botCredentials(), $http);
        $post = new Post(title: 'Thread reply', body: '');

        $platform->publish($post, [
            'thread_ts' => '1503435956.000247',
            'username' => 'CustomBot',
            'icon_emoji' => ':robot_face:',
            'unfurl_links' => false,
        ]);
    }

    #[Test]
    public function it_returns_failure_on_api_error(): void
    {
        $http = $this->mockHttp([
            'status' => 200,
            'headers' => [],
            'body' => json_encode(['ok' => false, 'error' => 'channel_not_found']),
        ]);

        $platform = new SlackPlatform($this->botCredentials(), $http);
        $post = new Post(title: 'Test', body: '');

        $result = $platform->publish($post);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('channel_not_found', $result->errorMessage());
    }

    #[Test]
    public function it_allows_channel_override_via_options(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $this->assertSame('C999OVERRIDE', $options['json']['channel']);

                    return true;
                }),
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['ok' => true, 'channel' => 'C999OVERRIDE', 'ts' => '1.2']),
            ]);

        $platform = new SlackPlatform($this->botCredentials(), $http);
        $post = new Post(title: 'Override', body: '');

        $platform->publish($post, ['channel' => 'C999OVERRIDE']);
    }

    // -----------------------------------------------------------------------
    //  Webhook Publish
    // -----------------------------------------------------------------------

    #[Test]
    public function it_publishes_via_webhook(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                'https://hooks.slack.com/services/T00/B00/xxxx',
                $this->callback(function (array $options): bool {
                    $this->assertArrayHasKey('json', $options);
                    $this->assertArrayHasKey('text', $options['json']);

                    return true;
                }),
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => 'ok',
            ]);

        $platform = new SlackPlatform($this->webhookCredentials(), $http);
        $post = new Post(title: 'Webhook Post', body: 'Via webhook');

        $result = $platform->publish($post);

        $this->assertTrue($result->isSuccess());
        $this->assertStringStartsWith('webhook_', $result->externalId());
    }

    #[Test]
    public function it_publishes_webhook_with_blocks(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $this->assertArrayHasKey('blocks', $options['json']);
                    $this->assertArrayHasKey('text', $options['json']); // fallback

                    return true;
                }),
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => 'ok',
            ]);

        $platform = new SlackPlatform($this->webhookCredentials(), $http);
        $post = new Post(title: 'Block Webhook', body: 'Content');

        $result = $platform->publish($post, ['blocks' => true]);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_returns_failure_on_webhook_error(): void
    {
        $http = $this->mockHttp([
            'status' => 403,
            'headers' => [],
            'body' => 'invalid_token',
        ]);

        $platform = new SlackPlatform($this->webhookCredentials(), $http);
        $post = new Post(title: 'Fail', body: '');

        $result = $platform->publish($post);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('invalid_token', $result->errorMessage());
    }

    // -----------------------------------------------------------------------
    //  Delete
    // -----------------------------------------------------------------------

    #[Test]
    public function it_deletes_a_message_via_bot(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                'https://slack.com/api/chat.delete',
                $this->callback(function (array $options): bool {
                    $this->assertSame('C123ABC456', $options['json']['channel']);
                    $this->assertSame('1503435956.000247', $options['json']['ts']);

                    return true;
                }),
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['ok' => true, 'channel' => 'C123ABC456', 'ts' => '1503435956.000247']),
            ]);

        $platform = new SlackPlatform($this->botCredentials(), $http);

        $result = $platform->delete('C123ABC456:1503435956.000247');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_throws_on_delete_with_webhook_mode(): void
    {
        $http = $this->mockHttp(['status' => 200, 'headers' => [], 'body' => '']);

        $platform = new SlackPlatform($this->webhookCredentials(), $http);

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('webhooks do not support message deletion');

        $platform->delete('C123:1503435956.000247');
    }

    #[Test]
    public function it_throws_on_invalid_external_id_format(): void
    {
        $http = $this->mockHttp(['status' => 200, 'headers' => [], 'body' => '']);

        $platform = new SlackPlatform($this->botCredentials(), $http);

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage("Invalid Slack external ID format");

        $platform->delete('invalid-no-colon');
    }

    #[Test]
    public function it_throws_on_delete_api_error(): void
    {
        $http = $this->mockHttp([
            'status' => 200,
            'headers' => [],
            'body' => json_encode(['ok' => false, 'error' => 'message_not_found']),
        ]);

        $platform = new SlackPlatform($this->botCredentials(), $http);

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('message_not_found');

        $platform->delete('C123:1503435956.000247');
    }

    // -----------------------------------------------------------------------
    //  Validate Credentials
    // -----------------------------------------------------------------------

    #[Test]
    public function it_validates_bot_credentials_via_auth_test(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                'https://slack.com/api/auth.test',
                $this->callback(function (array $options): bool {
                    $this->assertStringContainsString('Bearer xoxb-test-token', $options['headers']['Authorization']);

                    return true;
                }),
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'ok' => true,
                    'url' => 'https://test.slack.com/',
                    'team' => 'Test',
                    'user' => 'bot',
                    'team_id' => 'T123',
                    'user_id' => 'U123',
                ]),
            ]);

        $platform = new SlackPlatform($this->botCredentials(), $http);

        $this->assertTrue($platform->validateCredentials());
    }

    #[Test]
    public function it_validates_webhook_url_format(): void
    {
        $http = $this->mockHttp(['status' => 200, 'headers' => [], 'body' => '']);

        $platform = new SlackPlatform($this->webhookCredentials(), $http);

        $this->assertTrue($platform->validateCredentials());
    }

    #[Test]
    public function it_rejects_invalid_webhook_url(): void
    {
        $credentials = new PlatformCredentials('slack', [
            'webhook_url' => 'https://not-slack.com/webhook',
        ]);

        $http = $this->mockHttp(['status' => 200, 'headers' => [], 'body' => '']);
        $platform = new SlackPlatform($credentials, $http);

        $this->assertFalse($platform->validateCredentials());
    }

    #[Test]
    public function it_returns_false_on_invalid_bot_token(): void
    {
        $http = $this->mockHttp([
            'status' => 200,
            'headers' => [],
            'body' => json_encode(['ok' => false, 'error' => 'invalid_auth']),
        ]);

        $platform = new SlackPlatform($this->botCredentials(), $http);

        $this->assertFalse($platform->validateCredentials());
    }

    // -----------------------------------------------------------------------
    //  Rate Limiting
    // -----------------------------------------------------------------------

    #[Test]
    public function it_throws_rate_limit_exception_with_retry_after(): void
    {
        $http = $this->mockHttp([
            'status' => 429,
            'headers' => ['Retry-After' => '30'],
            'body' => json_encode(['ok' => false, 'error' => 'ratelimited']),
        ]);

        $platform = new SlackPlatform($this->botCredentials(), $http);
        $post = new Post(title: 'Rate limited', body: '');

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('rate limit exceeded');

        $platform->publish($post);
    }

    #[Test]
    public function it_throws_rate_limit_on_delete(): void
    {
        $http = $this->mockHttp([
            'status' => 429,
            'headers' => ['Retry-After' => '10'],
            'body' => json_encode(['ok' => false, 'error' => 'ratelimited']),
        ]);

        $platform = new SlackPlatform($this->botCredentials(), $http);

        $this->expectException(RateLimitException::class);

        $platform->delete('C123:1503435956.000247');
    }

    // -----------------------------------------------------------------------
    //  Constraints
    // -----------------------------------------------------------------------

    #[Test]
    public function it_returns_constraints(): void
    {
        $http = $this->mockHttp(['status' => 200, 'headers' => [], 'body' => '{}']);

        $platform = new SlackPlatform($this->botCredentials(), $http);
        $constraints = $platform->constraints();

        $this->assertSame(40_000, $constraints['max_text_length']);
        $this->assertSame(10, $constraints['max_media_count']);
        $this->assertSame(50, $constraints['max_blocks']);
        $this->assertSame(100, $constraints['max_attachments']);
        $this->assertContains('image/jpeg', $constraints['supported_media_types']);
    }
}
