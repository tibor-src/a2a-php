<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class ExtensionSupportRequiredException extends A2AException
{
    public function __construct(string $uri)
    {
        parent::__construct(
            "Required extension is not supported: {$uri}",
            -32008,
            400,
            'EXTENSION_SUPPORT_REQUIRED',
            grpcStatus: 'FAILED_PRECONDITION',
        );
    }
}
