<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

use TiborSrc\A2A\Types\StreamResponse;
use TiborSrc\A2A\Types\TaskPushNotificationConfig;

final class NullPushNotificationSender implements PushNotificationSender
{
    public function send(TaskPushNotificationConfig $config, StreamResponse $payload): void
    {
        // Push delivery is optional at the transport layer.
    }
}
