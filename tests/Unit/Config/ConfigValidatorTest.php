<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Config\ConfigValidator;
use Synglify\Core\Config\PlatformCredentials;
use Synglify\Core\Config\SynglifyConfig;
use Synglify\Core\Exceptions\SynglifyException;

class ConfigValidatorTest extends TestCase
{
    private ConfigValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ConfigValidator();
    }

    public function testValidateReturnsEmptyForValidTelegramCredentials(): void
    {
        $creds = new PlatformCredentials('telegram', ['api_token' => 'tok']);

        $this->assertSame([], $this->validator->validate($creds));
    }

    public function testValidateReturnsMissingKeys(): void
    {
        $creds = new PlatformCredentials('twitter', ['consumer_key' => 'ck']);

        $missing = $this->validator->validate($creds);

        $this->assertContains('consumer_secret', $missing);
        $this->assertContains('access_token', $missing);
        $this->assertContains('access_token_secret', $missing);
        $this->assertNotContains('consumer_key', $missing);
    }

    public function testValidateReturnsEmptyForUnknownPlatform(): void
    {
        $creds = new PlatformCredentials('unknown_platform', []);

        $this->assertSame([], $this->validator->validate($creds));
    }

    public function testRegisterRequiredKeys(): void
    {
        $this->validator->registerRequiredKeys('mastodon', ['instance_url', 'access_token']);

        $creds = new PlatformCredentials('mastodon', []);
        $missing = $this->validator->validate($creds);

        $this->assertSame(['instance_url', 'access_token'], $missing);
    }

    public function testValidateConfigThrowsOnMissingCredentials(): void
    {
        $config = new SynglifyConfig([
            'telegram' => [],
            'facebook' => ['app_id' => 'id'],
        ]);

        $this->expectException(SynglifyException::class);
        $this->expectExceptionMessage('Invalid configuration');

        $this->validator->validateConfig($config);
    }

    public function testValidateConfigPassesWithValidCredentials(): void
    {
        $config = new SynglifyConfig([
            'telegram' => ['api_token' => 'tok'],
            'twitter' => [
                'consumer_key' => 'a',
                'consumer_secret' => 'b',
                'access_token' => 'c',
                'access_token_secret' => 'd',
            ],
        ]);

        // Should not throw
        $this->validator->validateConfig($config);
        $this->assertTrue(true);
    }
}
