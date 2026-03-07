<?php
// phpcs:disable WordPress.WP.AlternativeFunctions -- Test files for framework-agnostic library.

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Platforms\Tumblr;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Content\Post;
use Owlstack\Core\Exceptions\PlatformException;
use Owlstack\Core\Exceptions\RateLimitException;
use Owlstack\Core\Http\Contracts\HttpClientInterface;
use Owlstack\Core\Platforms\Tumblr\TumblrPlatform;

class TumblrPlatformTest extends TestCase
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
        return new PlatformCredentials('tumblr', [
            'access_token' => 'tumblr-test-token',
            'blog_identifier' => 'testblog.tumblr.com',
        ]);
    }

    private function successResponse(string $postId = '123456789'): array
    {
        return [
            'status' => 201,
            'headers' => [],
            'body' => json_encode([
                'meta' => ['status' => 201, 'msg' => 'Created'],
                'response' => ['id' => $postId],
            ]),
        ];
    }

    // -----------------------------------------------------------------------
    //  Platform Name & Constraints
    // -----------------------------------------------------------------------

    #[Test]
    public function it_returns_platform_name(): void
    {
        $platform = new TumblrPlatform(
            $this->credentials(),
            $this->mockHttp($this->successResponse()),
        );

        $this->assertSame('tumblr', $platform->name());
    }

    #[Test]
    public function it_returns_constraints(): void
    {
        $platform = new TumblrPlatform(
            $this->credentials(),
            $this->mockHttp($this->successResponse()),
        );

        $constraints = $platform->constraints();

        $this->assertSame(4_096, $constraints['max_text_length']);
        $this->assertSame(200, $constraints['max_title_length']);
        $this->assertContains('text', $constraints['supported_post_types']);
        $this->assertContains('image', $constraints['supported_post_types']);
        $this->assertContains('video', $constraints['supported_post_types']);
        $this->assertContains('link', $constraints['supported_post_types']);
        $this->assertContains('audio', $constraints['supported_post_types']);
        $this->assertSame(30, $constraints['max_tags']);
    }

    // -----------------------------------------------------------------------
    //  Text Post
    // -----------------------------------------------------------------------

    #[Test]
    public function it_publishes_text_post(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                'https://api.tumblr.com/v2/blog/testblog.tumblr.com/posts',
                $this->callback(function (array $options): bool {
                    $this->assertStringContainsString('Bearer tumblr-test-token', $options['headers']['Authorization']);
                    $this->assertSame('application/json', $options['headers']['Content-Type']);

                    $json = $options['json'];
                    $this->assertIsArray($json['content']);
                    $this->assertNotEmpty($json['content']);

                    // First block should be heading
                    $this->assertSame('text', $json['content'][0]['type']);
                    $this->assertSame('heading1', $json['content'][0]['subtype']);
                    $this->assertSame('Hello', $json['content'][0]['text']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new TumblrPlatform($this->credentials(), $http);
        $post = new Post(title: 'Hello', body: 'World');

        $result = $platform->publish($post);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('123456789', $result->externalId());
        $this->assertStringContainsString('testblog.tumblr.com/post/123456789', $result->externalUrl());
    }

    #[Test]
    public function it_publishes_text_post_with_url_block(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $blocks = $options['json']['content'];
                    // Find the URL block (indented subtype with link formatting)
                    $urlBlock = null;
                    foreach ($blocks as $block) {
                        if (($block['subtype'] ?? '') === 'indented') {
                            $urlBlock = $block;
                            break;
                        }
                    }
                    $this->assertNotNull($urlBlock);
                    $this->assertSame('https://example.com', $urlBlock['text']);
                    $this->assertSame('link', $urlBlock['formatting'][0]['type']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new TumblrPlatform($this->credentials(), $http);
        $post = new Post(title: '', body: 'Check this', url: 'https://example.com');

        $platform->publish($post);
    }

    #[Test]
    public function it_includes_tags_in_request(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $this->assertSame('tech,php', $options['json']['tags']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new TumblrPlatform($this->credentials(), $http);
        $post = new Post(title: '', body: 'Content', tags: ['tech', 'php']);

        $platform->publish($post);
    }

    #[Test]
    public function it_supports_post_state_option(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $this->assertSame('draft', $options['json']['state']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new TumblrPlatform($this->credentials(), $http);
        $post = new Post(title: 'Draft', body: 'Content');

        $platform->publish($post, ['state' => 'draft']);
    }

    #[Test]
    public function it_supports_slug_option(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $this->assertSame('my-custom-slug', $options['json']['slug']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new TumblrPlatform($this->credentials(), $http);
        $post = new Post(title: 'Post', body: 'Content');

        $platform->publish($post, ['slug' => 'my-custom-slug']);
    }

    // -----------------------------------------------------------------------
    //  Image Post
    // -----------------------------------------------------------------------

    #[Test]
    public function it_publishes_image_post(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $blocks = $options['json']['content'];
                    $this->assertSame('image', $blocks[0]['type']);
                    $this->assertSame('https://example.com/photo.jpg', $blocks[0]['media'][0]['url']);
                    $this->assertSame('A nice photo', $blocks[0]['alt_text']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new TumblrPlatform($this->credentials(), $http);
        $post = new Post(title: '', body: 'Caption text');

        $result = $platform->publish($post, [
            'post_type' => 'image',
            'image_url' => 'https://example.com/photo.jpg',
            'alt_text' => 'A nice photo',
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
                    $blocks = $options['json']['content'];
                    $this->assertSame('text', $blocks[0]['type']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new TumblrPlatform($this->credentials(), $http);
        $post = new Post(title: '', body: 'Fallback');

        $platform->publish($post, ['post_type' => 'image']);
    }

    // -----------------------------------------------------------------------
    //  Video Post
    // -----------------------------------------------------------------------

    #[Test]
    public function it_publishes_video_post(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $blocks = $options['json']['content'];
                    $this->assertSame('video', $blocks[0]['type']);
                    $this->assertSame('https://example.com/video.mp4', $blocks[0]['url']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new TumblrPlatform($this->credentials(), $http);
        $post = new Post(title: 'Video', body: 'Watch this');

        $result = $platform->publish($post, [
            'post_type' => 'video',
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
                    $blocks = $options['json']['content'];
                    $this->assertSame('text', $blocks[0]['type']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new TumblrPlatform($this->credentials(), $http);
        $post = new Post(title: '', body: 'Fallback');

        $platform->publish($post, ['post_type' => 'video']);
    }

    // -----------------------------------------------------------------------
    //  Audio Post
    // -----------------------------------------------------------------------

    #[Test]
    public function it_publishes_audio_post(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $blocks = $options['json']['content'];
                    $this->assertSame('audio', $blocks[0]['type']);
                    $this->assertSame('https://example.com/song.mp3', $blocks[0]['url']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new TumblrPlatform($this->credentials(), $http);
        $post = new Post(title: 'Song', body: 'Listen to this');

        $result = $platform->publish($post, [
            'post_type' => 'audio',
            'audio_url' => 'https://example.com/song.mp3',
        ]);

        $this->assertTrue($result->isSuccess());
    }

    // -----------------------------------------------------------------------
    //  Link Post
    // -----------------------------------------------------------------------

    #[Test]
    public function it_publishes_link_post(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $blocks = $options['json']['content'];
                    // Should have a link block
                    $linkBlock = null;
                    foreach ($blocks as $block) {
                        if ($block['type'] === 'link') {
                            $linkBlock = $block;
                            break;
                        }
                    }
                    $this->assertNotNull($linkBlock);
                    $this->assertSame('https://example.com/article', $linkBlock['url']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new TumblrPlatform($this->credentials(), $http);
        $post = new Post(title: 'Article', body: 'Great read');

        $result = $platform->publish($post, [
            'post_type' => 'link',
            'link_url' => 'https://example.com/article',
        ]);

        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function it_uses_post_url_for_link_when_link_url_not_set(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (array $options): bool {
                    $blocks = $options['json']['content'];
                    $linkBlock = null;
                    foreach ($blocks as $block) {
                        if ($block['type'] === 'link') {
                            $linkBlock = $block;
                            break;
                        }
                    }
                    $this->assertNotNull($linkBlock);
                    $this->assertSame('https://example.com/from-post', $linkBlock['url']);

                    return true;
                }),
            )
            ->willReturn($this->successResponse());

        $platform = new TumblrPlatform($this->credentials(), $http);
        $post = new Post(title: '', body: 'Content', url: 'https://example.com/from-post');

        $platform->publish($post, ['post_type' => 'link']);
    }

    // -----------------------------------------------------------------------
    //  Missing Post ID
    // -----------------------------------------------------------------------

    #[Test]
    public function it_fails_when_response_has_no_post_id(): void
    {
        $response = [
            'status' => 201,
            'headers' => [],
            'body' => json_encode(['meta' => ['status' => 201], 'response' => []]),
        ];

        $platform = new TumblrPlatform($this->credentials(), $this->mockHttp($response));
        $post = new Post(title: '', body: 'Hello');

        $result = $platform->publish($post);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('post ID', $result->errorMessage());
    }

    // -----------------------------------------------------------------------
    //  Post URL Construction
    // -----------------------------------------------------------------------

    #[Test]
    public function it_builds_post_url_with_domain_identifier(): void
    {
        $platform = new TumblrPlatform(
            $this->credentials(),
            $this->mockHttp($this->successResponse('999')),
        );
        $post = new Post(title: '', body: 'Test');

        $result = $platform->publish($post);

        // testblog.tumblr.com contains a dot, so URL is https://testblog.tumblr.com/post/999
        $this->assertSame('https://testblog.tumblr.com/post/999', $result->externalUrl());
    }

    #[Test]
    public function it_builds_post_url_with_short_identifier(): void
    {
        $creds = new PlatformCredentials('tumblr', [
            'access_token' => 'token',
            'blog_identifier' => 'myblog',
        ]);

        $platform = new TumblrPlatform(
            $creds,
            $this->mockHttp($this->successResponse('888')),
        );
        $post = new Post(title: '', body: 'Test');

        $result = $platform->publish($post);

        // "myblog" has no dot, so URL is https://myblog.tumblr.com/post/888
        $this->assertSame('https://myblog.tumblr.com/post/888', $result->externalUrl());
    }

    // -----------------------------------------------------------------------
    //  Delete
    // -----------------------------------------------------------------------

    #[Test]
    public function it_deletes_a_post(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('post')
            ->with(
                'https://api.tumblr.com/v2/blog/testblog.tumblr.com/post/delete',
                $this->callback(function (array $options): bool {
                    $this->assertStringContainsString('Bearer tumblr-test-token', $options['headers']['Authorization']);
                    $this->assertSame('123456', $options['json']['id']);

                    return true;
                }),
            )
            ->willReturn([
                'status' => 200,
                'headers' => [],
                'body' => json_encode(['meta' => ['status' => 200, 'msg' => 'OK'], 'response' => []]),
            ]);

        $platform = new TumblrPlatform($this->credentials(), $http);

        $this->assertTrue($platform->delete('123456'));
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
                'meta' => ['status' => 200, 'msg' => 'OK'],
                'response' => [
                    'blog' => [
                        'name' => 'testblog',
                        'title' => 'Test Blog',
                        'posts' => 42,
                    ],
                ],
            ]),
        ];

        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('get')
            ->with(
                'https://api.tumblr.com/v2/blog/testblog.tumblr.com/info',
                $this->callback(function (array $options): bool {
                    $this->assertStringContainsString('Bearer tumblr-test-token', $options['headers']['Authorization']);

                    return true;
                }),
            )
            ->willReturn($response);

        $platform = new TumblrPlatform($this->credentials(), $http);

        $this->assertTrue($platform->validateCredentials());
    }

    #[Test]
    public function it_returns_false_for_invalid_credentials(): void
    {
        $response = [
            'status' => 401,
            'headers' => [],
            'body' => json_encode(['meta' => ['status' => 401, 'msg' => 'Unauthorized']]),
        ];

        $platform = new TumblrPlatform($this->credentials(), $this->mockHttp($response));

        $this->assertFalse($platform->validateCredentials());
    }

    #[Test]
    public function it_returns_false_when_validation_throws(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('get')->willThrowException(new \RuntimeException('Connection failed'));

        $platform = new TumblrPlatform($this->credentials(), $http);

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
                'meta' => ['status' => 400, 'msg' => 'Bad Request'],
                'errors' => [['detail' => 'Invalid content blocks']],
            ]),
        ];

        $this->expectException(PlatformException::class);
        $this->expectExceptionMessage('Bad Request');

        $platform = new TumblrPlatform($this->credentials(), $this->mockHttp($response));
        $post = new Post(title: '', body: 'Hello');

        $platform->publish($post);
    }

    // -----------------------------------------------------------------------
    //  Rate Limit
    // -----------------------------------------------------------------------

    #[Test]
    public function it_throws_rate_limit_exception_on_429(): void
    {
        $response = [
            'status' => 429,
            'headers' => ['Retry-After' => '60'],
            'body' => json_encode([
                'meta' => ['status' => 429, 'msg' => 'Rate Limit Exceeded'],
            ]),
        ];

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Rate Limit Exceeded');

        $platform = new TumblrPlatform($this->credentials(), $this->mockHttp($response));
        $post = new Post(title: '', body: 'Hello');

        $platform->publish($post);
    }

    #[Test]
    public function it_throws_rate_limit_without_retry_after(): void
    {
        $response = [
            'status' => 429,
            'headers' => [],
            'body' => json_encode([
                'meta' => ['status' => 429, 'msg' => 'Limit reached'],
            ]),
        ];

        $this->expectException(RateLimitException::class);

        $platform = new TumblrPlatform($this->credentials(), $this->mockHttp($response));
        $post = new Post(title: '', body: 'Hello');

        $platform->publish($post);
    }
}
