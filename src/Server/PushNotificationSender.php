<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

use TiborSrc\A2A\Types\StreamResponse;
use TiborSrc\A2A\Types\TaskPushNotificationConfig;

interface PushNotificationSender
{
    public function send(TaskPushNotificationConfig $config, StreamResponse $payload): void;
}
