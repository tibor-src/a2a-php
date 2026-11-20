<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class VersionNotSupportedException extends A2AException
{
    public function __construct(string $version)
    {
        parent::__construct(
            "Protocol version is not supported: {$version}",
            -32009,
            400,
            'VERSION_NOT_SUPPORTED',
            grpcStatus: 'FAILED_PRECONDITION',
        );
    }
}
