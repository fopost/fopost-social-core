<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Platforms;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Exceptions\SynglifyException;
use Synglify\Core\Platforms\Contracts\PlatformInterface;
use Synglify\Core\Platforms\PlatformRegistry;

class PlatformRegistryTest extends TestCase
{
    public function testRegisterAndGet(): void
    {
        $registry = new PlatformRegistry();
        $platform = $this->createMockPlatform('telegram');

        $registry->register($platform);

        $this->assertSame($platform, $registry->get('telegram'));
    }

    public function testGetThrowsForUnregisteredPlatform(): void
    {
        $registry = new PlatformRegistry();

        $this->expectException(SynglifyException::class);
        $this->expectExceptionMessage("Platform 'unknown' is not registered");

        $registry->get('unknown');
    }

    public function testHas(): void
    {
        $registry = new PlatformRegistry();
        $registry->register($this->createMockPlatform('twitter'));

        $this->assertTrue($registry->has('twitter'));
        $this->assertFalse($registry->has('facebook'));
    }

    public function testNames(): void
    {
        $registry = new PlatformRegistry();
        $registry->register($this->createMockPlatform('telegram'));
        $registry->register($this->createMockPlatform('twitter'));

        $this->assertSame(['telegram', 'twitter'], $registry->names());
    }

    public function testAll(): void
    {
        $registry = new PlatformRegistry();
        $t = $this->createMockPlatform('telegram');
        $x = $this->createMockPlatform('twitter');

        $registry->register($t);
        $registry->register($x);

        $all = $registry->all();

        $this->assertCount(2, $all);
        $this->assertSame($t, $all['telegram']);
        $this->assertSame($x, $all['twitter']);
    }

    public function testRegisterOverwritesSameNamePlatform(): void
    {
        $registry = new PlatformRegistry();
        $old = $this->createMockPlatform('telegram');
        $new = $this->createMockPlatform('telegram');

        $registry->register($old);
        $registry->register($new);

        $this->assertSame($new, $registry->get('telegram'));
        $this->assertCount(1, $registry->all());
    }

    private function createMockPlatform(string $name): PlatformInterface
    {
        $mock = $this->createMock(PlatformInterface::class);
        $mock->method('name')->willReturn($name);
        return $mock;
    }
}
