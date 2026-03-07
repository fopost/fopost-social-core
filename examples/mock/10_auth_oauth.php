<?php
// phpcs:disable WordPress,Universal.Operators.DisallowShortTernary -- Example files for framework-agnostic library.

declare(strict_types=1);

/**
 * Example 10: Authentication & OAuth
 *
 * Shows AccessToken, OAuthHandler, and how framework packages
 * implement the OAuthProviderInterface and TokenStoreInterface.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Owlstack\Core\Auth\AccessToken;
use Owlstack\Core\Auth\Contracts\OAuthProviderInterface;
use Owlstack\Core\Auth\Contracts\TokenStoreInterface;
use Owlstack\Core\Auth\OAuthHandler;

echo "=== Example 10: Authentication & OAuth ===\n\n";

// ── 1. AccessToken basics ───────────────────────────────────────────────
echo "1) AccessToken\n";

$token = new AccessToken(
    token: 'eyJhbGciOiJSUzI1NiJ9.example-jwt-token',
    refreshToken: 'rt_abc123',
    expiresAt: new DateTimeImmutable('+1 hour'),
    scopes: ['read', 'write', 'dm'],
    metadata: ['user_id' => '12345'],
);

echo "   token      : " . mb_substr($token->token, 0, 30) . "...\n";
echo "   isExpired() : " . ($token->isExpired() ? 'yes' : 'no') . "\n";
echo "   isRefreshable(): " . ($token->isRefreshable() ? 'yes' : 'no') . "\n";
echo "   scopes     : " . implode(', ', $token->scopes) . "\n";
echo "   metadata   : " . json_encode($token->metadata) . "\n\n";

// Expired token
$expired = new AccessToken(
    token: 'old-token',
    expiresAt: new DateTimeImmutable('-1 hour'),
);
echo "   Expired token → isExpired(): " . ($expired->isExpired() ? 'yes' : 'no') . "\n";
echo "   No refresh    → isRefreshable(): " . ($expired->isRefreshable() ? 'yes' : 'no') . "\n\n";

// Never-expiring token
$permanent = new AccessToken(token: 'permanent-token');
echo "   No expiry → isExpired(): " . ($permanent->isExpired() ? 'yes' : 'no') . "\n\n";

// ── 2. In-memory TokenStore (simulates a framework store) ───────────────
echo "2) In-Memory TokenStore\n";

$tokenStore = new class implements TokenStoreInterface {
    /** @var array<string, AccessToken> */
    private array $tokens = [];

    private function key(string $platform, string $accountId): string
    {
        return "{$platform}:{$accountId}";
    }

    public function get(string $platform, string $accountId): ?AccessToken
    {
        return $this->tokens[$this->key($platform, $accountId)] ?? null;
    }

    public function store(string $platform, string $accountId, AccessToken $token): void
    {
        $this->tokens[$this->key($platform, $accountId)] = $token;
    }

    public function revoke(string $platform, string $accountId): void
    {
        unset($this->tokens[$this->key($platform, $accountId)]);
    }

    public function has(string $platform, string $accountId): bool
    {
        return isset($this->tokens[$this->key($platform, $accountId)]);
    }
};

$tokenStore->store('twitter', 'user-42', $token);
echo "   Stored token for twitter:user-42\n";
echo "   has('twitter','user-42') : " . ($tokenStore->has('twitter', 'user-42') ? 'yes' : 'no') . "\n";
echo "   has('twitter','user-99') : " . ($tokenStore->has('twitter', 'user-99') ? 'yes' : 'no') . "\n";

$retrieved = $tokenStore->get('twitter', 'user-42');
echo "   Retrieved token: " . mb_substr($retrieved->token, 0, 30) . "...\n";

$tokenStore->revoke('twitter', 'user-42');
echo "   After revoke, has(): " . ($tokenStore->has('twitter', 'user-42') ? 'yes' : 'no') . "\n\n";

// ── 3. Mock OAuthProvider ───────────────────────────────────────────────
echo "3) OAuthHandler\n";

$oauthProvider = new class implements OAuthProviderInterface {
    public function getAuthorizationUrl(string $redirectUri, array $scopes = []): string
    {
        $q = http_build_query([
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'response_type' => 'code',
        ]);
        return "https://auth.example.com/authorize?{$q}";
    }

    public function exchangeCode(string $code, string $redirectUri): AccessToken
    {
        // Simulate exchanging the code for a token
        return new AccessToken(
            token: 'new-access-token-for-' . $code,
            refreshToken: 'new-refresh-token',
            expiresAt: new DateTimeImmutable('+2 hours'),
            scopes: ['read', 'write'],
        );
    }

    public function refreshToken(AccessToken $token): AccessToken
    {
        return new AccessToken(
            token: 'refreshed-' . $token->token,
            refreshToken: $token->refreshToken,
            expiresAt: new DateTimeImmutable('+2 hours'),
            scopes: $token->scopes,
        );
    }
};

$handler = new OAuthHandler($oauthProvider, $tokenStore, 'twitter');

// Step 1: Generate auth URL
$authUrl = $handler->authorize('https://myapp.com/callback', ['read', 'write']);
echo "   Auth URL: {$authUrl}\n";

// Step 2: Simulate callback with authorization code
$newToken = $handler->handleCallback('auth-code-xyz', 'https://myapp.com/callback', 'user-42');
echo "   Exchanged token: {$newToken->token}\n";
echo "   Stored? " . ($tokenStore->has('twitter', 'user-42') ? 'yes' : 'no') . "\n";

// Step 3: Get token (should return the stored one)
$fetched = $handler->getToken('user-42');
echo "   getToken(): {$fetched->token}\n";

// Step 4: Try getting a token that doesn't exist
echo "\n   Getting token for unknown account...\n";
try {
    $handler->getToken('non-existent');
} catch (\Owlstack\Core\Exceptions\AuthenticationException $e) {
    echo "   Caught: {$e->getMessage()}\n";
}

echo "\n=== Done ===\n";
