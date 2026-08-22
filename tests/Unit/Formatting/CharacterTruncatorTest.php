<?php

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Formatting;

use PHPUnit\Framework\TestCase;
use Fopost\Social\Formatting\CharacterTruncator;

class CharacterTruncatorTest extends TestCase
{
    private CharacterTruncator $truncator;

    protected function setUp(): void
    {
        $this->truncator = new CharacterTruncator();
    }

    public function testShortTextIsNotTruncated(): void
    {
        $this->assertSame('Hello', $this->truncator->truncate('Hello', 100));
    }

    public function testTextAtExactLimitIsNotTruncated(): void
    {
        $text = str_repeat('A', 50);
        $this->assertSame($text, $this->truncator->truncate($text, 50));
    }

    public function testLongTextIsTruncatedWithEllipsis(): void
    {
        $text = 'This is a long sentence that should be truncated';
        $result = $this->truncator->truncate($text, 20);

        $this->assertLessThanOrEqual(20, mb_strlen($result));
        $this->assertStringEndsWith('…', $result);
    }

    public function testTruncatesAtWordBoundary(): void
    {
        $text = 'Hello world this is test';
        $result = $this->truncator->truncate($text, 15);

        // Should break at a space, not mid-word
        $this->assertStringNotContainsString('thi', $result);
    }

    public function testCustomSuffix(): void
    {
        $text = 'Hello world this is a test sentence';
        $result = $this->truncator->truncate($text, 20, '...');

        $this->assertStringEndsWith('...', $result);
        $this->assertLessThanOrEqual(20, mb_strlen($result));
    }

    public function testCustomEllipsisInConstructor(): void
    {
        $truncator = new CharacterTruncator('...');
        $text = 'Hello world this is test';
        $result = $truncator->truncate($text, 15);

        $this->assertStringEndsWith('...', $result);
    }

    public function testMultibyteCharacters(): void
    {
        $text = 'こんにちは世界テスト文字列';
        $result = $this->truncator->truncate($text, 8);

        $this->assertLessThanOrEqual(8, mb_strlen($result));
    }
}
