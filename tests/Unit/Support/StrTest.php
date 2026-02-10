<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Support\Str;

class StrTest extends TestCase
{
    public function testLimitShortString(): void
    {
        $this->assertSame('Hello', Str::limit('Hello', 100));
    }

    public function testLimitTruncatesLongString(): void
    {
        $result = Str::limit('Hello World Example', 12);

        $this->assertLessThanOrEqual(12, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
    }

    public function testLimitCustomEnd(): void
    {
        $result = Str::limit('Hello World Example', 15, '...');

        $this->assertStringEndsWith('...', $result);
    }

    public function testSlug(): void
    {
        $this->assertSame('hello-world', Str::slug('Hello World'));
    }

    public function testSlugRemovesSpecialCharacters(): void
    {
        $this->assertSame('hello-world', Str::slug('Hello! @World#'));
    }

    public function testSlugCustomSeparator(): void
    {
        $this->assertSame('hello_world', Str::slug('Hello World', '_'));
    }

    public function testStartsWith(): void
    {
        $this->assertTrue(Str::startsWith('Hello World', 'Hello'));
        $this->assertFalse(Str::startsWith('Hello World', 'World'));
    }

    public function testStartsWithEmptyNeedle(): void
    {
        $this->assertTrue(Str::startsWith('Hello', ''));
    }
}
