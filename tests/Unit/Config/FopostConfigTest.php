<?php

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Fopost\Social\Config\PlatformCredentials;
use Fopost\Social\Config\FopostConfig;

class FopostConfigTest extends TestCase
{
    public function testCredentialsFromArray(): void
    {
        $config = new FopostConfig([
            'telegram' => ['api_token' => 'tok'],
        ]);

        $creds = $config->credentials('telegram');

        $this->assertInstanceOf(PlatformCredentials::class, $creds);
        $this->assertSame('tok', $creds->get('api_token'));
    }

    public function testCredentialsFromPlatformCredentialsInstance(): void
    {
        $creds = new PlatformCredentials('twitter', ['consumer_key' => 'ck']);
        $config = new FopostConfig(['twitter' => $creds]);

        $this->assertSame($creds, $config->credentials('twitter'));
    }

    public function testCredentialsReturnsNullForUnknownPlatform(): void
    {
        $config = new FopostConfig([]);

        $this->assertNull($config->credentials('unknown'));
    }

    public function testHasPlatform(): void
    {
        $config = new FopostConfig(['telegram' => ['api_token' => 'x']]);

        $this->assertTrue($config->hasPlatform('telegram'));
        $this->assertFalse($config->hasPlatform('twitter'));
    }

    public function testConfiguredPlatforms(): void
    {
        $config = new FopostConfig([
            'telegram' => ['api_token' => 'x'],
            'facebook' => ['app_id' => 'y'],
        ]);

        $this->assertSame(['telegram', 'facebook'], $config->configuredPlatforms());
    }

    public function testOptionReturnsValueOrDefault(): void
    {
        $config = new FopostConfig([], ['debug' => true]);

        $this->assertTrue($config->option('debug'));
        $this->assertSame('fallback', $config->option('missing', 'fallback'));
    }
}
