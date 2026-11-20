<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

use TiborSrc\A2A\Types\Message;
use TiborSrc\A2A\Types\Task;
use TiborSrc\A2A\Types\TaskArtifactUpdateEvent;
use TiborSrc\A2A\Types\TaskStatusUpdateEvent;

final class EventQueue
{
    /**
     * @var list<Task|Message|TaskStatusUpdateEvent|TaskArtifactUpdateEvent>
     */
    private array $events = [];

    public function write(Task|Message|TaskStatusUpdateEvent|TaskArtifactUpdateEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<Task|Message|TaskStatusUpdateEvent|TaskArtifactUpdateEvent>
     */
    public function events(): array
    {
        return $this->events;
    }
}
