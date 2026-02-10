<?php

declare(strict_types=1);

namespace Synglify\Core\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Synglify\Core\Config\PlatformCredentials;

class PlatformCredentialsTest extends TestCase
{
    public function testGetReturnsValueByKey(): void
    {
        $creds = new PlatformCredentials('telegram', ['api_token' => 'abc123']);

        $this->assertSame('abc123', $creds->get('api_token'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $creds = new PlatformCredentials('telegram', []);

        $this->assertSame('fallback', $creds->get('api_token', 'fallback'));
        $this->assertNull($creds->get('api_token'));
    }

    public function testHasReturnsTrueForExistingNonEmptyKey(): void
    {
        $creds = new PlatformCredentials('telegram', ['api_token' => 'abc']);

        $this->assertTrue($creds->has('api_token'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $creds = new PlatformCredentials('telegram', []);

        $this->assertFalse($creds->has('api_token'));
    }

    public function testHasReturnsFalseForEmptyStringValue(): void
    {
        $creds = new PlatformCredentials('telegram', ['api_token' => '']);

        $this->assertFalse($creds->has('api_token'));
    }

    public function testAllReturnsAllCredentials(): void
    {
        $data = ['key1' => 'val1', 'key2' => 'val2'];
        $creds = new PlatformCredentials('test', $data);

        $this->assertSame($data, $creds->all());
    }

    public function testRequireReturnsValueWhenPresent(): void
    {
        $creds = new PlatformCredentials('twitter', ['consumer_key' => 'ck']);

        $this->assertSame('ck', $creds->require('consumer_key'));
    }

    public function testRequireThrowsWhenKeyMissing(): void
    {
        $creds = new PlatformCredentials('twitter', []);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing required credential 'consumer_key'");

        $creds->require('consumer_key');
    }

    public function testPlatformNameIsReadonly(): void
    {
        $creds = new PlatformCredentials('facebook', []);

        $this->assertSame('facebook', $creds->platform);
    }
}
