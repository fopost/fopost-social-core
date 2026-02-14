<?php

declare(strict_types=1);

namespace Owlstack\Core\Auth\Contracts;

use Owlstack\Core\Auth\AccessToken;

/**
 * Contract for storing and retrieving OAuth tokens.
 *
 * Framework packages implement this with their own storage backends
 * (Eloquent, WP options, database, file, etc.).
 */
interface TokenStoreInterface
{
    /**
     * Retrieve a stored access token for a platform and account.
     */
    public function get(string $platform, string $accountId): ?AccessToken;

    /**
     * Store an access token for a platform and account.
     */
    public function store(string $platform, string $accountId, AccessToken $token): void;

    /**
     * Revoke/delete a stored access token.
     */
    public function revoke(string $platform, string $accountId): void;

    /**
     * Check if a token exists for a platform and account.
     */
    public function has(string $platform, string $accountId): bool;
}
