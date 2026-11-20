<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class ExtendedAgentCardNotConfiguredException extends A2AException
{
    public function __construct()
    {
        parent::__construct(
            'An extended agent card is not configured',
            -32007,
            400,
            'EXTENDED_AGENT_CARD_NOT_CONFIGURED',
            grpcStatus: 'FAILED_PRECONDITION',
        );
    }
}
