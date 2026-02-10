<?php

declare(strict_types=1);

namespace Synglify\Core\Auth\Contracts;

use Synglify\Core\Auth\AccessToken;

/**
 * Contract for OAuth provider implementations.
 *
 * Each platform that requires OAuth (Twitter, Facebook, etc.)
 * provides its own implementation of this interface.
 */
interface OAuthProviderInterface
{
    /**
     * Get the authorization URL to redirect the user to.
     *
     * @param string $redirectUri The callback URL after authorization.
     * @param array  $scopes      Requested permission scopes.
     * @return string The authorization URL.
     */
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string;

    /**
     * Exchange an authorization code for an access token.
     *
     * @param string $code        The authorization code from the callback.
     * @param string $redirectUri The same redirect URI used in getAuthorizationUrl.
     */
    public function exchangeCode(string $code, string $redirectUri): AccessToken;

    /**
     * Refresh an expired access token.
     */
    public function refreshToken(AccessToken $token): AccessToken;
}
