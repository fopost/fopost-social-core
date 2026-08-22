<?php

declare(strict_types=1);

namespace Fopost\Social\Exceptions;

/**
 * Thrown when authentication fails (token expired, invalid credentials, etc.).
 */
class AuthenticationException extends FopostException
{
}
