<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class TaskNotFoundException extends A2AException
{
    public function __construct(string $taskId)
    {
        parent::__construct(
            'The specified task ID does not exist or is not accessible',
            -32001,
            404,
            'TASK_NOT_FOUND',
            [[
                '@type' => 'type.googleapis.com/google.rpc.ErrorInfo',
                'reason' => 'TASK_NOT_FOUND',
                'domain' => 'a2a-protocol.org',
                'metadata' => ['taskId' => $taskId],
            ]],
            grpcStatus: 'NOT_FOUND',
        );
    }
}
