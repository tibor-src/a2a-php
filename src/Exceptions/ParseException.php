<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class ParseException extends A2AException
{
    public function __construct(string $message = 'Invalid JSON payload')
    {
        parent::__construct($message, -32700, 400);
    }
}
