<?php

declare(strict_types=1);

namespace Fopost\Social\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Fopost\Social\Auth\AccessToken;
use Fopost\Social\Auth\Contracts\OAuthProviderInterface;
use Fopost\Social\Auth\Contracts\TokenStoreInterface;
use Fopost\Social\Auth\OAuthHandler;
use Fopost\Social\Exceptions\AuthenticationException;

class OAuthHandlerTest extends TestCase
{
    private OAuthProviderInterface $provider;
    private TokenStoreInterface $store;
    private OAuthHandler $handler;

    protected function setUp(): void
    {
        $this->provider = $this->createMock(OAuthProviderInterface::class);
        $this->store = $this->createMock(TokenStoreInterface::class);
        $this->handler = new OAuthHandler($this->provider, $this->store, 'twitter');
    }

    public function testAuthorizeReturnsUrl(): void
    {
        $this->provider->expects($this->once())
            ->method('getAuthorizationUrl')
            ->with('https://callback.test', ['read', 'write'])
            ->willReturn('https://auth.example.com/authorize?state=abc');

        $url = $this->handler->authorize('https://callback.test', ['read', 'write']);

        $this->assertSame('https://auth.example.com/authorize?state=abc', $url);
    }

    public function testHandleCallbackExchangesAndStoresToken(): void
    {
        $token = new AccessToken(token: 'access_tok');

        $this->provider->expects($this->once())
            ->method('exchangeCode')
            ->with('auth_code', 'https://callback.test')
            ->willReturn($token);

        $this->store->expects($this->once())
            ->method('store')
            ->with('twitter', 'user123', $token);

        $result = $this->handler->handleCallback('auth_code', 'https://callback.test', 'user123');

        $this->assertSame($token, $result);
    }

    public function testGetTokenReturnsStoredToken(): void
    {
        $token = new AccessToken(token: 'valid_tok', expiresAt: new DateTimeImmutable('+1 hour'));

        $this->store->method('get')
            ->with('twitter', 'user123')
            ->willReturn($token);

        $result = $this->handler->getToken('user123');

        $this->assertSame($token, $result);
    }

    public function testGetTokenThrowsWhenNotFound(): void
    {
        $this->store->method('get')->willReturn(null);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("No token found for platform 'twitter' account 'user123'");

        $this->handler->getToken('user123');
    }

    public function testGetTokenRefreshesExpiredToken(): void
    {
        $expired = new AccessToken(
            token: 'old_tok',
            refreshToken: 'refresh_tok',
            expiresAt: new DateTimeImmutable('-1 hour'),
        );
        $refreshed = new AccessToken(
            token: 'new_tok',
            expiresAt: new DateTimeImmutable('+1 hour'),
        );

        $this->store->method('get')->willReturn($expired);

        $this->provider->expects($this->once())
            ->method('refreshToken')
            ->with($expired)
            ->willReturn($refreshed);

        $this->store->expects($this->once())
            ->method('store')
            ->with('twitter', 'user123', $refreshed);

        $result = $this->handler->getToken('user123');

        $this->assertSame($refreshed, $result);
    }

    public function testGetTokenDoesNotRefreshWhenNoRefreshToken(): void
    {
        $expired = new AccessToken(
            token: 'old_tok',
            refreshToken: null,
            expiresAt: new DateTimeImmutable('-1 hour'),
        );

        $this->store->method('get')->willReturn($expired);

        $this->provider->expects($this->never())->method('refreshToken');

        $result = $this->handler->getToken('user123');

        $this->assertSame($expired, $result);
    }
}
