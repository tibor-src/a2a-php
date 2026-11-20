<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class ContentTypeNotSupportedException extends A2AException
{
    public function __construct(string $mediaType)
    {
        parent::__construct(
            "Content type is not supported: {$mediaType}",
            -32005,
            400,
            'CONTENT_TYPE_NOT_SUPPORTED',
            grpcStatus: 'INVALID_ARGUMENT',
        );
    }
}
