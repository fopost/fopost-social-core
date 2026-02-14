<?php

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Support;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Owlstack\Core\Support\Clock;

class ClockTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::unfreeze();
    }

    public function testNowReturnsCurrentTime(): void
    {
        $before = new DateTimeImmutable();
        $now = Clock::now();
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }

    public function testFreezeFixesTime(): void
    {
        $fixed = new DateTimeImmutable('2025-01-01 12:00:00');
        Clock::freeze($fixed);

        $this->assertSame($fixed->getTimestamp(), Clock::now()->getTimestamp());
        $this->assertSame($fixed->getTimestamp(), Clock::timestamp());
    }

    public function testUnfreezeResumesNormalClock(): void
    {
        $fixed = new DateTimeImmutable('2020-01-01 00:00:00');
        Clock::freeze($fixed);
        Clock::unfreeze();

        $now = Clock::now();
        $this->assertGreaterThan($fixed->getTimestamp(), $now->getTimestamp());
    }

    public function testNowWithTimezone(): void
    {
        $tz = new DateTimeZone('America/New_York');
        $now = Clock::now($tz);

        $this->assertSame('America/New_York', $now->getTimezone()->getName());
    }

    public function testFrozenTimeWithTimezone(): void
    {
        $fixed = new DateTimeImmutable('2025-06-15 12:00:00', new DateTimeZone('UTC'));
        Clock::freeze($fixed);

        $tokyo = Clock::now(new DateTimeZone('Asia/Tokyo'));
        $this->assertSame('Asia/Tokyo', $tokyo->getTimezone()->getName());
        $this->assertSame($fixed->getTimestamp(), $tokyo->getTimestamp());
    }

    public function testTimestampReturnsInteger(): void
    {
        $this->assertIsInt(Clock::timestamp());
    }

    public function testFreezeWithoutArgumentUsesCurrentTime(): void
    {
        $before = time();
        Clock::freeze();
        $frozen = Clock::timestamp();

        $this->assertGreaterThanOrEqual($before, $frozen);
        $this->assertLessThanOrEqual($before + 1, $frozen);
    }
}
