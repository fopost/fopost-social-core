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

    // ── Extended Telegram methods ───────────────────────────────────────────

    public function testSendLocation(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('sendLocation'),
                $this->callback(function (array $options) {
                    $p = $options['form_params'];
                    return $p['chat_id'] === '@test_channel'
                        && $p['latitude'] === 51.5074
                        && $p['longitude'] === -0.1278;
                })
            )
            ->willReturn($this->successResponse(['message_id' => 200]));

        $result = $this->platform->sendLocation('@test_channel', 51.5074, -0.1278);

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['result']['message_id']);
    }

    public function testSendLocationWithOptions(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('sendLocation'),
                $this->callback(function (array $options) {
                    $p = $options['form_params'];
                    return $p['live_period'] === 600
                        && $p['disable_notification'] === true;
                })
            )
            ->willReturn($this->successResponse(['message_id' => 201]));

        $this->platform->sendLocation('@test_channel', 51.5074, -0.1278, [
            'live_period' => 600,
            'disable_notification' => true,
        ]);
    }

    public function testSendVenue(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('sendVenue'),
                $this->callback(function (array $options) {
                    $p = $options['form_params'];
                    return $p['chat_id'] === '@test_channel'
                        && $p['title'] === 'Test Venue'
                        && $p['address'] === '123 Test St';
                })
            )
            ->willReturn($this->successResponse(['message_id' => 210]));

        $result = $this->platform->sendVenue('@test_channel', 51.5074, -0.1278, 'Test Venue', '123 Test St');

        $this->assertTrue($result['ok']);
    }

    public function testSendVenueWithFoursquareId(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('sendVenue'),
                $this->callback(function (array $options) {
                    return $options['form_params']['foursquare_id'] === '4bf58dd8d48988d1';
                })
            )
            ->willReturn($this->successResponse(['message_id' => 211]));

        $this->platform->sendVenue('@test_channel', 51.5074, -0.1278, 'Venue', 'Address', [
            'foursquare_id' => '4bf58dd8d48988d1',
        ]);
    }

    public function testSendContact(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('sendContact'),
                $this->callback(function (array $options) {
                    $p = $options['form_params'];
                    return $p['chat_id'] === '@test_channel'
                        && $p['phone_number'] === '+1234567890'
                        && $p['first_name'] === 'John';
                })
            )
            ->willReturn($this->successResponse(['message_id' => 220]));

        $result = $this->platform->sendContact('@test_channel', '+1234567890', 'John');

        $this->assertTrue($result['ok']);
    }

    public function testSendContactWithLastName(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('sendContact'),
                $this->callback(function (array $options) {
                    return $options['form_params']['last_name'] === 'Doe';
                })
            )
            ->willReturn($this->successResponse(['message_id' => 221]));

        $this->platform->sendContact('@test_channel', '+1234567890', 'John', [
            'last_name' => 'Doe',
        ]);
    }

    public function testSendVoice(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('sendVoice'),
                $this->callback(function (array $options) {
                    $p = $options['form_params'];
                    return $p['chat_id'] === '@test_channel'
                        && $p['voice'] === '/path/to/voice.ogg';
                })
            )
            ->willReturn($this->successResponse(['message_id' => 230]));

        $result = $this->platform->sendVoice('@test_channel', '/path/to/voice.ogg');

        $this->assertTrue($result['ok']);
    }

    public function testSendVoiceWithOptions(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('sendVoice'),
                $this->callback(function (array $options) {
                    $p = $options['form_params'];
                    return $p['caption'] === 'Voice note'
                        && $p['duration'] === 15
                        && $p['parse_mode'] === 'HTML';
                })
            )
            ->willReturn($this->successResponse(['message_id' => 231]));

        $this->platform->sendVoice('@test_channel', '/path/to/voice.ogg', [
            'caption' => 'Voice note',
            'duration' => 15,
            'parse_mode' => 'HTML',
        ]);
    }

    public function testEditMessageText(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('editMessageText'),
                $this->callback(function (array $options) {
                    $p = $options['form_params'];
                    return $p['chat_id'] === '@test_channel'
                        && $p['message_id'] === 42
                        && $p['text'] === 'Updated text'
                        && $p['parse_mode'] === 'HTML';
                })
            )
            ->willReturn($this->successResponse(['message_id' => 42]));

        $result = $this->platform->editMessageText('@test_channel', 42, 'Updated text');

        $this->assertTrue($result['ok']);
    }

    public function testEditMessageTextWithKeyboard(): void
    {
        $keyboard = [[['text' => 'Button', 'url' => 'https://example.com']]];

        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('editMessageText'),
                $this->callback(function (array $options) use ($keyboard) {
                    $p = $options['form_params'];
                    $markup = json_decode($p['reply_markup'], true);
                    return $markup['inline_keyboard'] === $keyboard;
                })
            )
            ->willReturn($this->successResponse(['message_id' => 42]));

        $this->platform->editMessageText('@test_channel', 42, 'Updated', [
            'inline_keyboard' => $keyboard,
        ]);
    }

    public function testEditMessageCaption(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('editMessageCaption'),
                $this->callback(function (array $options) {
                    $p = $options['form_params'];
                    return $p['chat_id'] === '@test_channel'
                        && $p['message_id'] === 55
                        && $p['caption'] === 'New caption';
                })
            )
            ->willReturn($this->successResponse(['message_id' => 55]));

        $result = $this->platform->editMessageCaption('@test_channel', 55, 'New caption');

        $this->assertTrue($result['ok']);
    }

    public function testPinMessage(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('pinChatMessage'),
                $this->callback(function (array $options) {
                    $p = $options['form_params'];
                    return $p['chat_id'] === '@test_channel'
                        && $p['message_id'] === 42;
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['ok' => true, 'result' => true]),
            ]);

        $this->assertTrue($this->platform->pinMessage('@test_channel', 42));
    }

    public function testPinMessageWithSilentNotification(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('pinChatMessage'),
                $this->callback(function (array $options) {
                    return $options['form_params']['disable_notification'] === true;
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['ok' => true, 'result' => true]),
            ]);

        $this->platform->pinMessage('@test_channel', 42, ['disable_notification' => true]);
    }

    public function testUnpinMessage(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('unpinChatMessage'),
                $this->callback(function (array $options) {
                    $p = $options['form_params'];
                    return $p['chat_id'] === '@test_channel'
                        && $p['message_id'] === 42;
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['ok' => true, 'result' => true]),
            ]);

        $this->assertTrue($this->platform->unpinMessage('@test_channel', 42));
    }

    public function testUnpinAllMessages(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('unpinAllChatMessages'),
                $this->callback(function (array $options) {
                    return $options['form_params']['chat_id'] === '@test_channel';
                })
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['ok' => true, 'result' => true]),
            ]);

        $this->assertTrue($this->platform->unpinAllMessages('@test_channel'));
    }

    public function testSendLocationThrowsOnApiError(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn([
                'status' => 400,
                'headers' => [],
                'body' => json_encode([
                    'ok' => false,
                    'description' => 'Bad Request: invalid latitude',
                    'error_code' => 400,
                ]),
            ]);

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('Bad Request: invalid latitude');

        $this->platform->sendLocation('@test_channel', 999.0, 999.0);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function successResponse(array $result): array
    {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode(['ok' => true, 'result' => $result]),
        ];
    }
}
