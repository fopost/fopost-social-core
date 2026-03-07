<?php

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Framework-agnostic library; exceptions are not WordPress output.

namespace Owlstack\Core\Auth;

use Owlstack\Core\Auth\Contracts\OAuthProviderInterface;
use Owlstack\Core\Auth\Contracts\TokenStoreInterface;
use Owlstack\Core\Exceptions\AuthenticationException;

/**
 * Manages the OAuth flow using provider and token store contracts.
 */
class OAuthHandler
{
    public function __construct(
        private readonly OAuthProviderInterface $provider,
        private readonly TokenStoreInterface $tokenStore,
        private readonly string $platform,
    ) {
    }

    /**
     * Start the OAuth flow by generating the authorization URL.
     */
    public function authorize(string $redirectUri, array $scopes = []): string
    {
        return $this->provider->getAuthorizationUrl($redirectUri, $scopes);
    }

    /**
     * Handle the OAuth callback — exchange the code and store the token.
     */
    public function handleCallback(string $code, string $redirectUri, string $accountId): AccessToken
    {
        $token = $this->provider->exchangeCode($code, $redirectUri);
        $this->tokenStore->store($this->platform, $accountId, $token);

        return $token;
    }

    /**
     * Get a valid token for an account, refreshing if expired.
     *
     * @throws AuthenticationException If no token exists for the account.
     */
    public function getToken(string $accountId): AccessToken
    {
        $token = $this->tokenStore->get($this->platform, $accountId);

        if ($token === null) {
            throw new AuthenticationException(
                "No token found for platform '{$this->platform}' account '{$accountId}'."
            );
        }

        if ($token->isExpired() && $token->refreshToken !== null) {
            $token = $this->provider->refreshToken($token);
            $this->tokenStore->store($this->platform, $accountId, $token);
        }

        return $token;
    }
}
