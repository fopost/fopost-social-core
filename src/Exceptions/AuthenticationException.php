<?php

declare(strict_types=1);

namespace Owlstack\Core\Exceptions;

/**
 * Thrown when authentication fails (token expired, invalid credentials, etc.).
 */
class AuthenticationException extends OwlstackException
{
}
