<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

final class PushNotificationNotSupportedException extends A2AException
{
    public function __construct()
    {
        parent::__construct(
            'Push notifications are not supported by this agent',
            -32003,
            400,
            'PUSH_NOTIFICATION_NOT_SUPPORTED',
            grpcStatus: 'FAILED_PRECONDITION',
        );
    }
}
