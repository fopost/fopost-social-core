<?php

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Platforms;

use PHPUnit\Framework\TestCase;
use Owlstack\Core\Platforms\PlatformResponse;

class PlatformResponseTest extends TestCase
{
    public function testSuccessFactoryMethod(): void
    {
        $response = PlatformResponse::success('123', 'https://example.com/123', ['ok' => true]);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('123', $response->externalId());
        $this->assertSame('https://example.com/123', $response->externalUrl());
        $this->assertSame(['ok' => true], $response->rawResponse());
        $this->assertNull($response->errorMessage());
    }

    public function testFailureFactoryMethod(): void
    {
        $response = PlatformResponse::failure('Something went wrong', ['error' => true]);

        $this->assertFalse($response->isSuccess());
        $this->assertNull($response->externalId());
        $this->assertNull($response->externalUrl());
        $this->assertSame('Something went wrong', $response->errorMessage());
        $this->assertSame(['error' => true], $response->rawResponse());
    }

    public function testSuccessWithMinimalArguments(): void
    {
        $response = PlatformResponse::success('456');

        $this->assertTrue($response->isSuccess());
        $this->assertSame('456', $response->externalId());
        $this->assertNull($response->externalUrl());
        $this->assertSame([], $response->rawResponse());
    }

    public function testFailureWithMinimalArguments(): void
    {
        $response = PlatformResponse::failure('Error');

        $this->assertFalse($response->isSuccess());
        $this->assertSame('Error', $response->errorMessage());
        $this->assertSame([], $response->rawResponse());
    }
}
