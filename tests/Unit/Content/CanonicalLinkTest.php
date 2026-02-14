<?php

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use Owlstack\Core\Content\CanonicalLink;

class CanonicalLinkTest extends TestCase
{
    public function testGenerateWithDefaultTemplate(): void
    {
        $link = new CanonicalLink();

        $result = $link->generate('https://example.com');

        $this->assertSame("\n\nRead more: https://example.com", $result);
    }

    public function testGenerateWithCustomTemplate(): void
    {
        $link = new CanonicalLink(' | {url}');

        $this->assertSame(' | https://example.com', $link->generate('https://example.com'));
    }

    public function testInjectWhenContentFits(): void
    {
        $link = new CanonicalLink();

        $result = $link->inject('Hello', 'https://example.com', 500);

        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testInjectTruncatesWhenContentTooLong(): void
    {
        $link = new CanonicalLink();
        $longContent = str_repeat('A', 200);

        $result = $link->inject($longContent, 'https://example.com', 100);

        $this->assertLessThanOrEqual(100, mb_strlen($result));
        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testInjectReturnsOnlyLinkWhenNoRoomForContent(): void
    {
        $link = new CanonicalLink();

        // Link itself is ~36 chars, limit is 36
        $result = $link->inject('Some content', 'https://example.com', 36);

        $this->assertStringContainsString('https://example.com', $result);
    }
}
