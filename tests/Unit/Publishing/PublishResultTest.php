<?php

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Publishing;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Fopost\Social\Publishing\PublishResult;

class PublishResultTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $result = new PublishResult(
            success: true,
            platformName: 'telegram',
            externalId: '12345',
            externalUrl: 'https://t.me/ch/12345',
        );

        $this->assertTrue($result->success);
        $this->assertFalse($result->failed());
        $this->assertSame('telegram', $result->platformName);
        $this->assertSame('12345', $result->externalId);
        $this->assertSame('https://t.me/ch/12345', $result->externalUrl);
        $this->assertNull($result->error);
        $this->assertInstanceOf(DateTimeImmutable::class, $result->timestamp);
    }

    public function testFailedResult(): void
    {
        $result = new PublishResult(
            success: false,
            platformName: 'twitter',
            error: 'Rate limit exceeded',
        );

        $this->assertFalse($result->success);
        $this->assertTrue($result->failed());
        $this->assertSame('Rate limit exceeded', $result->error);
        $this->assertNull($result->externalId);
        $this->assertNull($result->externalUrl);
    }

    public function testCustomTimestamp(): void
    {
        $ts = new DateTimeImmutable('2025-03-15 10:00:00');
        $result = new PublishResult(
            success: true,
            platformName: 'facebook',
            timestamp: $ts,
        );

        $this->assertSame($ts, $result->timestamp);
    }

    public function testDefaultTimestampIsNow(): void
    {
        $before = new DateTimeImmutable();
        $result = new PublishResult(success: true, platformName: 'test');
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $result->timestamp->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $result->timestamp->getTimestamp());
    }
}
