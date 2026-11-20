<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class InvalidParamsException extends A2AException
{
    public function __construct(string $message = 'Invalid parameters')
    {
        parent::__construct($message, -32602, 400);
    }

    public static function forField(string $field, string $description): self
    {
        return new self("Invalid parameters: {$field}: {$description}");
    }
}
