<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class MethodNotFoundException extends A2AException
{
    public function __construct(string $method)
    {
        parent::__construct("Method not found: {$method}", -32601, 404);
    }
}
