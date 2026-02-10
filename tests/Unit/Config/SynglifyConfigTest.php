<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Config\SynglifyConfig;

class SynglifyConfigTest extends TestCase
{
    public function testCredentialsFromArray(): void
    {
        $config = new SynglifyConfig([
            'telegram' => ['api_token' => 'tok'],
        ]);

        $creds = $config->credentials('telegram');

        $this->assertInstanceOf(PlatformCredentials::class, $creds);
        $this->assertSame('tok', $creds->get('api_token'));
    }

    public function testCredentialsFromPlatformCredentialsInstance(): void
    {
        $creds = new PlatformCredentials('twitter', ['consumer_key' => 'ck']);
        $config = new SynglifyConfig(['twitter' => $creds]);

        $this->assertSame($creds, $config->credentials('twitter'));
    }

    public function testCredentialsReturnsNullForUnknownPlatform(): void
    {
        $config = new SynglifyConfig([]);

        $this->assertNull($config->credentials('unknown'));
    }

    public function testHasPlatform(): void
    {
        $config = new SynglifyConfig(['telegram' => ['api_token' => 'x']]);

        $this->assertTrue($config->hasPlatform('telegram'));
        $this->assertFalse($config->hasPlatform('twitter'));
    }

    public function testConfiguredPlatforms(): void
    {
        $config = new SynglifyConfig([
            'telegram' => ['api_token' => 'x'],
            'facebook' => ['app_id' => 'y'],
        ]);

        $this->assertSame(['telegram', 'facebook'], $config->configuredPlatforms());
    }

    public function testOptionReturnsValueOrDefault(): void
    {
        $config = new SynglifyConfig([], ['debug' => true]);

        $this->assertTrue($config->option('debug'));
        $this->assertSame('fallback', $config->option('missing', 'fallback'));
    }
}
