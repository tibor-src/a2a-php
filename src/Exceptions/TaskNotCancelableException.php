<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class TaskNotCancelableException extends A2AException
{
    public function __construct(string $taskId)
    {
        parent::__construct(
            'The task cannot be canceled',
            -32002,
            400,
            'TASK_NOT_CANCELABLE',
            [[
                '@type' => 'type.googleapis.com/google.rpc.ErrorInfo',
                'reason' => 'TASK_NOT_CANCELABLE',
                'domain' => 'a2a-protocol.org',
                'metadata' => ['taskId' => $taskId],
            ]],
            grpcStatus: 'FAILED_PRECONDITION',
        );
    }
}
