<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Support\Arr;

class ArrTest extends TestCase
{
    public function testGetTopLevelKey(): void
    {
        $this->assertSame('bar', Arr::get(['foo' => 'bar'], 'foo'));
    }

    public function testGetNestedKey(): void
    {
        $data = ['a' => ['b' => ['c' => 'deep']]];
        $this->assertSame('deep', Arr::get($data, 'a.b.c'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $this->assertSame('default', Arr::get([], 'missing', 'default'));
    }

    public function testGetReturnsDefaultForBrokenPath(): void
    {
        $data = ['a' => 'not_an_array'];
        $this->assertNull(Arr::get($data, 'a.b'));
    }

    public function testFilterEmpty(): void
    {
        $result = Arr::filterEmpty(['a' => 'keep', 'b' => null, 'c' => '', 'd' => 0, 'e' => false]);

        $this->assertSame(['a' => 'keep', 'd' => 0, 'e' => false], $result);
    }

    public function testOnly(): void
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];

        $this->assertSame(['a' => 1, 'c' => 3], Arr::only($data, ['a', 'c']));
    }

    public function testOnlyIgnoresMissingKeys(): void
    {
        $data = ['a' => 1];

        $this->assertSame(['a' => 1], Arr::only($data, ['a', 'z']));
    }
}
