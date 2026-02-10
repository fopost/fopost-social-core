<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Platforms\Telegram;

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
use Synglify\Core\Platforms\Telegram\TelegramFormatter;
use Synglify\Core\Platforms\Telegram\TelegramPlatform;

class TelegramPlatformTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private TelegramPlatform $platform;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $credentials = new PlatformCredentials('telegram', [
            'api_token' => 'test-token-123',
            'channel_username' => '@test_channel',
        ]);

        $formatter = new TelegramFormatter(
            new HashtagExtractor(),
            new CharacterTruncator(),
        );

        $this->platform = new TelegramPlatform($credentials, $this->httpClient, $formatter);
    }

    public function testName(): void
    {
        $this->assertSame('telegram', $this->platform->name());
    }

    public function testPublishTextMessage(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('test-token-123/sendMessage'),
                $this->callback(function (array $options) {
                    return isset($options['form_params']['chat_id'])
                        && isset($options['form_params']['text'])
                        && $options['form_params']['chat_id'] === '@test_channel';
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'ok' => true,
                    'result' => ['message_id' => 42],
                ]),
            ]);

        $post = new Post(title: 'Hello', body: 'World');
        $response = $this->platform->publish($post);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('42', $response->externalId());
    }

    public function testPublishWithSingleImage(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('sendPhoto'),
                $this->callback(function (array $options) {
                    return isset($options['form_params']['photo'])
                        && isset($options['form_params']['caption']);
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'ok' => true,
                    'result' => ['message_id' => 55],
                ]),
            ]);

        $media = new MediaCollection([
            new Media('/path/to/image.jpg', 'image/jpeg'),
        ]);
        $post = new Post(title: 'Photo', body: 'A photo post', media: $media);
        $response = $this->platform->publish($post);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('55', $response->externalId());
    }

    public function testPublishMediaGroup(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('sendMediaGroup'),
                $this->callback(function (array $options) {
                    return isset($options['form_params']['media']);
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'ok' => true,
                    'result' => [
                        ['message_id' => 101],
                        ['message_id' => 102],
                    ],
                ]),
            ]);

        $media = new MediaCollection([
            new Media('/path/to/img1.jpg', 'image/jpeg'),
            new Media('/path/to/img2.jpg', 'image/jpeg'),
        ]);
        $post = new Post(title: 'Album', body: 'Multiple photos', media: $media);
        $response = $this->platform->publish($post);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('101', $response->externalId());
    }

    public function testDeleteMessage(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('deleteMessage'),
                $this->callback(function (array $options) {
                    return $options['form_params']['message_id'] === 42;
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['ok' => true]),
            ]);

        $this->assertTrue($this->platform->delete('42'));
    }

    public function testValidateCredentials(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with($this->stringContains('getMe'))
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode([
                    'ok' => true,
                    'result' => ['id' => 123, 'first_name' => 'Bot'],
                ]),
            ]);

        $this->assertTrue($this->platform->validateCredentials());
    }

    public function testValidateCredentialsReturnsFalseOnError(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 401,
                'headers' => [],
                'body' => json_encode(['ok' => false, 'description' => 'Unauthorized']),
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
                    'ok' => false,
                    'description' => 'Bad Request: chat not found',
                    'error_code' => 400,
                ]),
            ]);

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('Bad Request: chat not found');

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
                    'ok' => false,
                    'description' => 'Too Many Requests',
                    'parameters' => ['retry_after' => 30],
                ]),
            ]);

        $this->expectException(RateLimitException::class);

        $post = new Post(title: 'Test', body: 'Content');
        $this->platform->publish($post);
    }

    public function testConstraints(): void
    {
        $constraints = $this->platform->constraints();

        $this->assertSame(4096, $constraints['max_text_length']);
        $this->assertSame(1024, $constraints['max_caption_length']);
        $this->assertSame(10, $constraints['max_media_count']);
        $this->assertContains('image/jpeg', $constraints['supported_media_types']);
        $this->assertContains('video/mp4', $constraints['supported_media_types']);
    }
}
