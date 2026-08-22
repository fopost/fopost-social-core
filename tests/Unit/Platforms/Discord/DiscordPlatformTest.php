<?php
// phpcs:disable WordPress.WP.AlternativeFunctions -- Test files for framework-agnostic library.

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Platforms\Discord;

use PHPUnit\Framework\TestCase;
use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Content\Post;
use Fopost\Social\Exceptions\PlatformException;
use Fopost\Social\Exceptions\RateLimitException;
use Fopost\Social\Formatting\CharacterTruncator;
use Fopost\Social\Formatting\HashtagExtractor;
use Fopost\Social\Http\Contracts\HttpClientInterface;
use Fopost\Social\Platforms\Discord\DiscordFormatter;
use Fopost\Social\Platforms\Discord\DiscordPlatform;

class DiscordPlatformTest extends TestCase
{
    private HttpClientInterface $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
    }

    private function createBotPlatform(): DiscordPlatform
    {
        $credentials = new PlatformCredentials('discord', [
            'bot_token' => 'test-bot-token',
            'channel_id' => '123456789012345678',
        ]);

        return new DiscordPlatform(
            $credentials,
            $this->httpClient,
            new DiscordFormatter(new HashtagExtractor(), new CharacterTruncator()),
        );
    }

    private function createWebhookPlatform(): DiscordPlatform
    {
        $credentials = new PlatformCredentials('discord', [
            'webhook_url' => 'https://discord.com/api/webhooks/123/abc-token',
        ]);

        return new DiscordPlatform(
            $credentials,
            $this->httpClient,
            new DiscordFormatter(new HashtagExtractor(), new CharacterTruncator()),
        );
    }

    public function testName(): void
    {
        $this->assertSame('discord', $this->createBotPlatform()->name());
    }

    public function testConstraints(): void
    {
        $constraints = $this->createBotPlatform()->constraints();

        $this->assertSame(2000, $constraints['max_text_length']);
        $this->assertSame(10, $constraints['max_media_count']);
        $this->assertContains('image/jpeg', $constraints['supported_media_types']);
    }

    public function testPublishViaBotWithEmbed(): void
    {
        $platform = $this->createBotPlatform();

        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/channels/123456789012345678/messages'),
                $this->callback(function (array $options) {
                    return isset($options['json']['embeds'])
                        && count($options['json']['embeds']) === 1
                        && $options['json']['embeds'][0]['title'] === 'Test Post'
                        && str_contains($options['headers']['Authorization'], 'Bot test-bot-token');
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'id' => '999888777666555444',
                    'channel_id' => '123456789012345678',
                    'guild_id' => '111222333444555666',
                ]),
            ]);

        $post = new Post(title: 'Test Post', body: 'Hello Discord!');
        $response = $platform->publish($post);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('999888777666555444', $response->externalId());
        $this->assertStringContainsString('discord.com/channels', $response->externalUrl());
    }

    public function testPublishViaBotPlainText(): void
    {
        $platform = $this->createBotPlatform();

        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return isset($options['json']['content'])
                        && !isset($options['json']['embeds']);
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'id' => '111222333',
                    'channel_id' => '123456789012345678',
                ]),
            ]);

        $post = new Post(title: 'Plain', body: 'No embed');
        $response = $platform->publish($post, ['embed' => false]);

        $this->assertTrue($response->isSuccess());
    }

    public function testPublishViaWebhook(): void
    {
        $platform = $this->createWebhookPlatform();

        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('discord.com/api/webhooks/123/abc-token?wait=true'),
                $this->callback(function (array $options) {
                    return isset($options['json']['embeds']);
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'id' => '555666777888',
                    'channel_id' => '987654321',
                    'guild_id' => '111222333',
                ]),
            ]);

        $post = new Post(title: 'Webhook Post', body: 'Via webhook');
        $response = $platform->publish($post);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('555666777888', $response->externalId());
    }

    public function testPublishViaWebhookWithOptions(): void
    {
        $platform = $this->createWebhookPlatform();

        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) {
                    return $options['json']['username'] === 'Owlstack Bot'
                        && $options['json']['tts'] === true;
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['id' => '123', 'channel_id' => '456']),
            ]);

        $post = new Post(title: 'Custom', body: 'Content');
        $platform->publish($post, [
            'username' => 'Owlstack Bot',
            'tts' => true,
        ]);
    }

    public function testDeleteViaBot(): void
    {
        $platform = $this->createBotPlatform();

        $this->httpClient
            ->expects($this->once())
            ->method('delete')
            ->with(
                $this->stringContains('/channels/123456789012345678/messages/999888777'),
                $this->callback(function (array $options) {
                    return str_contains($options['headers']['Authorization'], 'Bot test-bot-token');
                })
            )
            ->willReturn([
                'status' => 204,
                'headers' => [],
                'body' => '',
            ]);

        $this->assertTrue($platform->delete('999888777'));
    }

    public function testDeleteViaWebhook(): void
    {
        $platform = $this->createWebhookPlatform();

        $this->httpClient
            ->expects($this->once())
            ->method('delete')
            ->with(
                $this->stringContains('webhooks/123/abc-token/messages/555666'),
                $this->anything()
            )
            ->willReturn([
                'status' => 204,
                'headers' => [],
                'body' => '',
            ]);

        $this->assertTrue($platform->delete('555666'));
    }

    public function testDeleteReturnsFalseOnError(): void
    {
        $platform = $this->createBotPlatform();

        $this->httpClient
            ->method('delete')
            ->willReturn([
                'status' => 404,
                'headers' => [],
                'body' => json_encode(['message' => 'Unknown Message']),
            ]);

        $this->assertFalse($platform->delete('nonexistent'));
    }

    public function testValidateCredentialsBotMode(): void
    {
        $platform = $this->createBotPlatform();

        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->with(
                $this->stringContains('/users/@me'),
                $this->callback(function (array $options) {
                    return str_contains($options['headers']['Authorization'], 'Bot test-bot-token');
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['id' => '123', 'username' => 'OwlstackBot']),
            ]);

        $this->assertTrue($platform->validateCredentials());
    }

    public function testValidateCredentialsBotModeFailure(): void
    {
        $platform = $this->createBotPlatform();

        $this->httpClient
            ->method('get')
            ->willReturn([
                'status' => 401,
                'headers' => [],
                'body' => json_encode(['message' => 'Unauthorized']),
            ]);

        $this->assertFalse($platform->validateCredentials());
    }

    public function testValidateCredentialsWebhookMode(): void
    {
        $platform = $this->createWebhookPlatform();

        // Webhook validation only checks URL format
        $this->assertTrue($platform->validateCredentials());
    }

    public function testValidateCredentialsWebhookInvalidUrl(): void
    {
        $credentials = new PlatformCredentials('discord', [
            'webhook_url' => 'https://example.com/not-discord',
        ]);

        $platform = new DiscordPlatform(
            $credentials,
            $this->httpClient,
            new DiscordFormatter(new HashtagExtractor(), new CharacterTruncator()),
        );

        $this->assertFalse($platform->validateCredentials());
    }

    public function testPublishThrowsRateLimitException(): void
    {
        $platform = $this->createBotPlatform();

        $this->httpClient
            ->method('post')
            ->willReturn([
                'status' => 429,
                'headers' => [],
                'body' => json_encode([
                    'message' => 'You are being rate limited.',
                    'retry_after' => 5,
                ]),
            ]);

        $post = new Post(title: 'Rate Limited', body: 'Too fast');

        $this->expectException(RateLimitException::class);

        $platform->publish($post);
    }

    public function testPublishThrowsOnAuthError(): void
    {
        $platform = $this->createBotPlatform();

        $this->httpClient
            ->method('post')
            ->willReturn([
                'status' => 401,
                'headers' => [],
                'body' => json_encode([
                    'message' => 'Unauthorized',
                    'code' => 0,
                ]),
            ]);

        $post = new Post(title: 'Auth Fail', body: 'Bad token');

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('Unauthorized');

        $platform->publish($post);
    }

    public function testPublishThrowsOnGenericError(): void
    {
        $platform = $this->createBotPlatform();

        $this->httpClient
            ->method('post')
            ->willReturn([
                'status' => 400,
                'headers' => [],
                'body' => json_encode([
                    'message' => 'Invalid Form Body',
                    'code' => 50035,
                ]),
            ]);

        $post = new Post(title: 'Error', body: 'Bad request');

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('Invalid Form Body');

        $platform->publish($post);
    }
}
