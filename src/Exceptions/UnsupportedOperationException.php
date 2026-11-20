<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class UnsupportedOperationException extends A2AException
{
    public function __construct(string $message = 'The requested operation is not supported')
    {
        parent::__construct($message, -32004, 400, 'UNSUPPORTED_OPERATION', grpcStatus: 'FAILED_PRECONDITION');
    }
}
