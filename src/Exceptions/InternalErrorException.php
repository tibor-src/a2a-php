<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class InternalErrorException extends A2AException
{
    public function __construct(string $message = 'Internal error')
    {
        parent::__construct($message, -32603, 500);
    }
}
