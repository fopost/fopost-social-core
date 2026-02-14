<?php

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Platforms\WhatsApp;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Exceptions\PlatformException;
use Owlstack\Core\Exceptions\RateLimitException;
use Owlstack\Core\Http\Contracts\HttpClientInterface;
use Owlstack\Core\Platforms\WhatsApp\WhatsAppPlatform;

class WhatsAppPlatformTest extends TestCase
{
    private function mockHttp(array $response): HttpClientInterface
    {
        $http = $this->createMock(HttpClientInterface::class);

        $http->method('post')->willReturn($response);
        $http->method('get')->willReturn($response);
        $http->method('delete')->willReturn($response);

        return $http;
    }

    private function credentials(): PlatformCredentials
    {
        return new PlatformCredentials('whatsapp', [
            'access_token' => 'wa-test-token-123',
            'phone_number_id' => '123456789',
        ]);
    }

    private function successResponse(string $messageId = 'wamid.test123'): array
    {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'messaging_product' => 'whatsapp',
                'contacts' => [
                    ['input' => '+14155238886', 'wa_id' => '14155238886'],
                ],
                'messages' => [
                    ['id' => $messageId],
                ],
            ]),
        ];
    }

    // -----------------------------------------------------------------------
    //  Platform Name
    // -----------------------------------------------------------------------

    #[Test]
    public function it_returns_platform_name(): void
    {
        $platform = new WhatsAppPlatform(
            $this->credentials(),
            $this->mockHttp($this->successResponse()),
        );

        $this->assertSame('whatsapp', $platform->name());
    }

    // -----------------------------------------------------------------------
    //  Constraints
    // -----------------------------------------------------------------------

    #[Test]
    public function it_returns_constraints(): void
    {
        $platform = new WhatsAppPlatform(
            $this->credentials(),
            $this->mockHttp($this->successResponse()),
        );

        $constraints = $platform->constraints();

        $this->assertSame(4_096, $constraints['max_text_length']);
        $this->assertSame(1_024, $constraints['max_caption_length']);
        $this->assertIsArray($constraints['supported_media_types']);
        $this->assertContains('image/jpeg', $constraints['supported_media_types']);
    }

    // -----------------------------------------------------------------------
    //  Text Message
    // -----------------------------------------------------------------------

    #[Test]
    public function it_publishes_text_message(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                'https://graph.facebook.com/v19.0/123456789/messages',
                $this->callback(function (array $options): bool {
                    $this->assertStringContainsString('Bearer wa-test-token-123', $options['headers']['Authorization']);
                    $this->assertSame('application/json', $options['headers']['Content-Type']);

                    $json = $options['json'];
                    $this->assertSame('whatsapp', $json['messaging_product']);
                    $this->assertSame('individual', $json['recipient_type']);
                    $this->assertSame('+14155238886', $json['to']);
                    $this->assertSame('text', $json['type']);
                    $this->assertTrue($json['text']['preview_url']);
                    $this->assertStringContainsString('Hello', $json['text']['body']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new WhatsAppPlatform($this->credentials(), $http);
        $post = new Post(title: 'Hello', body: 'World');

        $result = $platform->publish($post, ['to' => '+14155238886']);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('wamid.test123', $result->externalId());
    }

    #[Test]
    public function it_publishes_text_message_with_preview_disabled(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $this->assertFalse($options['json']['text']['preview_url']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new WhatsAppPlatform($this->credentials(), $http);
        $post = new Post(title: '', body: 'No preview');

        $result = $platform->publish($post, ['to' => '+14155238886', 'preview_url' => false]);

        $this->assertTrue($result->isSuccess());
    }

    // -----------------------------------------------------------------------
    //  Image Message
    // -----------------------------------------------------------------------

    #[Test]
    public function it_publishes_image_message_with_caption(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $json = $options['json'];
                    $this->assertSame('image', $json['type']);
                    $this->assertSame('https://example.com/photo.jpg', $json['image']['link']);
                    $this->assertArrayHasKey('caption', $json['image']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new WhatsAppPlatform($this->credentials(), $http);
        $post = new Post(title: 'Photo', body: 'A nice photo');

        $result = $platform->publish($post, [
            'to' => '+14155238886',
            'message_type' => 'image',
            'image_url' => 'https://example.com/photo.jpg',
        ]);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_falls_back_to_text_when_image_url_missing(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $this->assertSame('text', $options['json']['type']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new WhatsAppPlatform($this->credentials(), $http);
        $post = new Post(title: '', body: 'Fallback text');

        $result = $platform->publish($post, [
            'to' => '+14155238886',
            'message_type' => 'image',
        ]);

        $this->assertTrue($result->isSuccess());
    }

    // -----------------------------------------------------------------------
    //  Video Message
    // -----------------------------------------------------------------------

    #[Test]
    public function it_publishes_video_message_with_caption(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $json = $options['json'];
                    $this->assertSame('video', $json['type']);
                    $this->assertSame('https://example.com/video.mp4', $json['video']['link']);
                    $this->assertArrayHasKey('caption', $json['video']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new WhatsAppPlatform($this->credentials(), $http);
        $post = new Post(title: 'Video', body: 'Watch this');

        $result = $platform->publish($post, [
            'to' => '+14155238886',
            'message_type' => 'video',
            'video_url' => 'https://example.com/video.mp4',
        ]);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_falls_back_to_text_when_video_url_missing(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $this->assertSame('text', $options['json']['type']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new WhatsAppPlatform($this->credentials(), $http);
        $post = new Post(title: '', body: 'Fallback');

        $result = $platform->publish($post, [
            'to' => '+14155238886',
            'message_type' => 'video',
        ]);

        $this->assertTrue($result->isSuccess());
    }

    // -----------------------------------------------------------------------
    //  Document Message
    // -----------------------------------------------------------------------

    #[Test]
    public function it_publishes_document_message_with_filename(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $json = $options['json'];
                    $this->assertSame('document', $json['type']);
                    $this->assertSame('https://example.com/report.pdf', $json['document']['link']);
                    $this->assertSame('report.pdf', $json['document']['filename']);
                    $this->assertArrayHasKey('caption', $json['document']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new WhatsAppPlatform($this->credentials(), $http);
        $post = new Post(title: 'Report', body: 'Monthly report');

        $result = $platform->publish($post, [
            'to' => '+14155238886',
            'message_type' => 'document',
            'document_url' => 'https://example.com/report.pdf',
            'filename' => 'report.pdf',
        ]);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_falls_back_to_text_when_document_url_missing(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $this->assertSame('text', $options['json']['type']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new WhatsAppPlatform($this->credentials(), $http);
        $post = new Post(title: '', body: 'Fallback');

        $result = $platform->publish($post, [
            'to' => '+14155238886',
            'message_type' => 'document',
        ]);

        $this->assertTrue($result->isSuccess());
    }

    // -----------------------------------------------------------------------
    //  Template Message
    // -----------------------------------------------------------------------

    #[Test]
    public function it_publishes_template_message(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $json = $options['json'];
                    $this->assertSame('template', $json['type']);
                    $this->assertSame('hello_world', $json['template']['name']);
                    $this->assertSame('en', $json['template']['language']['code']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new WhatsAppPlatform($this->credentials(), $http);
        $post = new Post(title: '', body: '');

        $result = $platform->publish($post, [
            'to' => '+14155238886',
            'message_type' => 'template',
            'template_name' => 'hello_world',
        ]);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_publishes_template_with_components(): void
    {
        $components = [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'John']]],
        ];

        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options) use ($components): bool {
                    $json = $options['json'];
                    $this->assertSame($components, $json['template']['components']);
                    $this->assertSame('pt_BR', $json['template']['language']['code']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new WhatsAppPlatform($this->credentials(), $http);
        $post = new Post(title: '', body: '');

        $result = $platform->publish($post, [
            'to' => '+14155238886',
            'message_type' => 'template',
            'template_name' => 'welcome',
            'template_lang' => 'pt_BR',
            'template_components' => $components,
        ]);

        $this->assertTrue($result->isSuccess());
    }

    // -----------------------------------------------------------------------
    //  Missing Recipient
    // -----------------------------------------------------------------------

    #[Test]
    public function it_fails_when_to_is_missing(): void
    {
        $platform = new WhatsAppPlatform(
            $this->credentials(),
            $this->mockHttp($this->successResponse()),
        );

        $post = new Post(title: '', body: 'Hello');
        $result = $platform->publish($post);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('phone number', $result->errorMessage());
    }

    #[Test]
    public function it_fails_when_to_is_empty_string(): void
    {
        $platform = new WhatsAppPlatform(
            $this->credentials(),
            $this->mockHttp($this->successResponse()),
        );

        $post = new Post(title: '', body: 'Hello');
        $result = $platform->publish($post, ['to' => '']);

        $this->assertFalse($result->isSuccess());
    }

    // -----------------------------------------------------------------------
    //  Missing Message ID in Response
    // -----------------------------------------------------------------------

    #[Test]
    public function it_fails_when_response_has_no_message_id(): void
    {
        $response = [
            'status' => 200,
            'headers' => [],
            'body' => json_encode(['messaging_product' => 'whatsapp']),
        ];

        $platform = new WhatsAppPlatform($this->credentials(), $this->mockHttp($response));
        $post = new Post(title: '', body: 'Hello');

        $result = $platform->publish($post, ['to' => '+14155238886']);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('message ID', $result->errorMessage());
    }

    // -----------------------------------------------------------------------
    //  Delete Not Supported
    // -----------------------------------------------------------------------

    #[Test]
    public function it_throws_on_delete(): void
    {
        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('does not support deleting');

        $platform = new WhatsAppPlatform(
            $this->credentials(),
            $this->mockHttp($this->successResponse()),
        );

        $platform->delete('wamid.test123');
    }

    // -----------------------------------------------------------------------
    //  Validate Credentials
    // -----------------------------------------------------------------------

    #[Test]
    public function it_validates_credentials_successfully(): void
    {
        $response = [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'id' => '123456789',
                'display_phone_number' => '+14155238886',
                'verified_name' => 'Test Business',
            ]),
        ];

        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('get')
            ->with(
                'https://graph.facebook.com/v19.0/123456789',
                $this->callback(function (array $options): bool {
                    $this->assertStringContainsString('Bearer wa-test-token-123', $options['headers']['Authorization']);
                    $this->assertSame('id,display_phone_number,verified_name', $options['query']['fields']);

                    return true;
                }),
            )
            ->willReturn($response);

        $platform = new WhatsAppPlatform($this->credentials(), $http);

        $this->assertTrue($platform->validateCredentials());
    }

    #[Test]
    public function it_returns_false_for_invalid_credentials(): void
    {
        $response = [
            'status' => 401,
            'headers' => [],
            'body' => json_encode(['error' => ['message' => 'Invalid token']]),
        ];

        $platform = new WhatsAppPlatform($this->credentials(), $this->mockHttp($response));

        $this->assertFalse($platform->validateCredentials());
    }

    #[Test]
    public function it_returns_false_when_validation_throws(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('get')->willThrowException(new \RuntimeException('Connection failed'));

        $platform = new WhatsAppPlatform($this->credentials(), $http);

        $this->assertFalse($platform->validateCredentials());
    }

    // -----------------------------------------------------------------------
    //  API Error Handling
    // -----------------------------------------------------------------------

    #[Test]
    public function it_throws_platform_exception_on_api_error(): void
    {
        $response = [
            'status' => 400,
            'headers' => [],
            'body' => json_encode([
                'error' => [
                    'message' => 'Invalid parameter',
                    'code' => 100,
                ],
            ]),
        ];

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('Invalid parameter');

        $platform = new WhatsAppPlatform($this->credentials(), $this->mockHttp($response));
        $post = new Post(title: '', body: 'Hello');

        $platform->publish($post, ['to' => '+14155238886']);
    }

    // -----------------------------------------------------------------------
    //  Rate Limit
    // -----------------------------------------------------------------------

    #[Test]
    public function it_throws_rate_limit_exception_on_http_429(): void
    {
        $response = [
            'status' => 429,
            'headers' => [],
            'body' => json_encode([
                'error' => [
                    'message' => 'Too many requests',
                    'code' => 4,
                ],
            ]),
        ];

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Too many requests');

        $platform = new WhatsAppPlatform($this->credentials(), $this->mockHttp($response));
        $post = new Post(title: '', body: 'Hello');

        $platform->publish($post, ['to' => '+14155238886']);
    }

    #[Test]
    public function it_throws_rate_limit_exception_on_error_code_130429(): void
    {
        $response = [
            'status' => 400,
            'headers' => [],
            'body' => json_encode([
                'error' => [
                    'message' => 'Rate limit hit',
                    'code' => 130429,
                ],
            ]),
        ];

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Rate limit hit');

        $platform = new WhatsAppPlatform($this->credentials(), $this->mockHttp($response));
        $post = new Post(title: '', body: 'Hello');

        $platform->publish($post, ['to' => '+14155238886']);
    }

    // -----------------------------------------------------------------------
    //  Graph Version
    // -----------------------------------------------------------------------

    #[Test]
    public function it_uses_custom_graph_version(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                'https://graph.facebook.com/v20.0/123456789/messages',
                $this->anything(),
            )
            ->willReturn($this->successResponse());

        $platform = new WhatsAppPlatform(
            $this->credentials(),
            $http,
            graphVersion: 'v20.0',
        );

        $post = new Post(title: '', body: 'Hello');
        $result = $platform->publish($post, ['to' => '+14155238886']);

        $this->assertTrue($result->isSuccess());
    }
}
