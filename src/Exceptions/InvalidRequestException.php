<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class InvalidRequestException extends A2AException
{
    public function __construct(string $message = 'Request payload validation error')
    {
        parent::__construct($message, -32600, 400);
    }
}
