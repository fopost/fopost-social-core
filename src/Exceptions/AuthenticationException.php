<?php

declare(strict_types=1);

namespace Synglify\Core\Exceptions;

/**
 * Thrown when authentication fails (token expired, invalid credentials, etc.).
 */
class AuthenticationException extends SynglifyException
{
}
