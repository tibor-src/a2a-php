<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class InvalidAgentResponseException extends A2AException
{
    public function __construct(string $message = 'The agent returned an invalid response')
    {
        parent::__construct($message, -32006, 500, 'INVALID_AGENT_RESPONSE', grpcStatus: 'INTERNAL');
    }
}
