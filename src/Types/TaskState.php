<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

enum TaskState: string
{
    case Unspecified = 'TASK_STATE_UNSPECIFIED';
    case Submitted = 'TASK_STATE_SUBMITTED';
    case Working = 'TASK_STATE_WORKING';
    case Completed = 'TASK_STATE_COMPLETED';
    case Failed = 'TASK_STATE_FAILED';
    case Canceled = 'TASK_STATE_CANCELED';
    case InputRequired = 'TASK_STATE_INPUT_REQUIRED';
    case Rejected = 'TASK_STATE_REJECTED';
    case AuthRequired = 'TASK_STATE_AUTH_REQUIRED';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Canceled, self::Rejected => true,
            default => false,
        };
    }

    public function isInterrupted(): bool
    {
        return $this === self::InputRequired || $this === self::AuthRequired;
    }
}
