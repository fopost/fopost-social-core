<?php

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Fopost\Social\Auth\AccessToken;

class AccessTokenTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $token = new AccessToken(token: 'abc123');

        $this->assertSame('abc123', $token->token);
        $this->assertNull($token->refreshToken);
        $this->assertNull($token->expiresAt);
        $this->assertSame([], $token->scopes);
        $this->assertSame([], $token->metadata);
    }

    public function testIsExpiredReturnsFalseWhenNoExpiry(): void
    {
        $token = new AccessToken(token: 'tok');

        $this->assertFalse($token->isExpired());
    }

    public function testIsExpiredReturnsTrueWhenPastExpiry(): void
    {
        $token = new AccessToken(
            token: 'tok',
            expiresAt: new DateTimeImmutable('2020-01-01 00:00:00'),
        );

        $this->assertTrue($token->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenFutureExpiry(): void
    {
        $token = new AccessToken(
            token: 'tok',
            expiresAt: new DateTimeImmutable('+1 hour'),
        );

        $this->assertFalse($token->isExpired());
    }

    public function testIsRefreshableReturnsTrueWithRefreshToken(): void
    {
        $token = new AccessToken(token: 'tok', refreshToken: 'refresh_tok');

        $this->assertTrue($token->isRefreshable());
    }

    public function testIsRefreshableReturnsFalseWithoutRefreshToken(): void
    {
        $token = new AccessToken(token: 'tok');

        $this->assertFalse($token->isRefreshable());
    }

    public function testIsRefreshableReturnsFalseWithEmptyRefreshToken(): void
    {
        $token = new AccessToken(token: 'tok', refreshToken: '');

        $this->assertFalse($token->isRefreshable());
    }

    public function testScopesAndMetadata(): void
    {
        $token = new AccessToken(
            token: 'tok',
            scopes: ['read', 'write'],
            metadata: ['provider' => 'twitter'],
        );

        $this->assertSame(['read', 'write'], $token->scopes);
        $this->assertSame(['provider' => 'twitter'], $token->metadata);
    }
}
