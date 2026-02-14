<?php

declare(strict_types=1);

namespace Owlstack\Core\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Owlstack\Core\Config\PlatformCredentials;
use Owlstack\Core\Config\OwlstackConfig;

class OwlstackConfigTest extends TestCase
{
    public function testCredentialsFromArray(): void
    {
        $config = new OwlstackConfig([
            'telegram' => ['api_token' => 'tok'],
        ]);

        $creds = $config->credentials('telegram');

        $this->assertInstanceOf(PlatformCredentials::class, $creds);
        $this->assertSame('tok', $creds->get('api_token'));
    }

    public function testCredentialsFromPlatformCredentialsInstance(): void
    {
        $creds = new PlatformCredentials('twitter', ['consumer_key' => 'ck']);
        $config = new OwlstackConfig(['twitter' => $creds]);

        $this->assertSame($creds, $config->credentials('twitter'));
    }

    public function testCredentialsReturnsNullForUnknownPlatform(): void
    {
        $config = new OwlstackConfig([]);

        $this->assertNull($config->credentials('unknown'));
    }

    public function testHasPlatform(): void
    {
        $config = new OwlstackConfig(['telegram' => ['api_token' => 'x']]);

        $this->assertTrue($config->hasPlatform('telegram'));
        $this->assertFalse($config->hasPlatform('twitter'));
    }

    public function testConfiguredPlatforms(): void
    {
        $config = new OwlstackConfig([
            'telegram' => ['api_token' => 'x'],
            'facebook' => ['app_id' => 'y'],
        ]);

        $this->assertSame(['telegram', 'facebook'], $config->configuredPlatforms());
    }

    public function testOptionReturnsValueOrDefault(): void
    {
        $config = new OwlstackConfig([], ['debug' => true]);

        $this->assertTrue($config->option('debug'));
        $this->assertSame('fallback', $config->option('missing', 'fallback'));
    }
}
