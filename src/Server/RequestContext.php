<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

use TiborSrc\A2A\Types\Message;
use TiborSrc\A2A\Types\SendMessageConfiguration;
use TiborSrc\A2A\Types\Task;

final readonly class RequestContext
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public Message $message,
        public ?Task $task = null,
        public ?SendMessageConfiguration $configuration = null,
        public ?array $metadata = null,
        public bool $streaming = false,
    ) {}

    public function taskId(): ?string
    {
        return $this->task instanceof Task ? $this->task->id : $this->message->taskId;
    }
}
