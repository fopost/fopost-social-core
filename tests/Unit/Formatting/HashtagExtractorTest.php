<?php

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Formatting;

use PHPUnit\Framework\TestCase;
use Fopost\Social\Formatting\HashtagExtractor;

class HashtagExtractorTest extends TestCase
{
    private HashtagExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new HashtagExtractor();
    }

    public function testExtractBasicTags(): void
    {
        $result = $this->extractor->extract(['php', 'owlstack']);

        $this->assertSame('#php #owlstack', $result);
    }

    public function testExtractStripsExistingHashSymbols(): void
    {
        $result = $this->extractor->extract(['#php', '##double']);

        $this->assertSame('#php #double', $result);
    }

    public function testExtractRemovesSpecialCharacters(): void
    {
        $result = $this->extractor->extract(['hello world', 'c++', 'node.js']);

        $this->assertSame('#helloworld #c #nodejs', $result);
    }

    public function testExtractFiltersEmptyTags(): void
    {
        $result = $this->extractor->extract(['php', '', '   ', 'dev']);

        $this->assertSame('#php #dev', $result);
    }

    public function testExtractWithMaxCount(): void
    {
        $result = $this->extractor->extract(['a', 'b', 'c', 'd'], 2);

        $this->assertSame('#a #b', $result);
    }

    public function testExtractWithZeroMaxCountReturnsAll(): void
    {
        $result = $this->extractor->extract(['a', 'b', 'c'], 0);

        $this->assertSame('#a #b #c', $result);
    }

    public function testExtractEmptyArray(): void
    {
        $this->assertSame('', $this->extractor->extract([]));
    }

    public function testExtractSupportsUnicode(): void
    {
        $result = $this->extractor->extract(['プログラミング', 'café']);

        $this->assertStringContainsString('#プログラミング', $result);
        $this->assertStringContainsString('#café', $result);
    }
}
