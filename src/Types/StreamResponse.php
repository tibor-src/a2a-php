<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Json\Payload;

final readonly class StreamResponse
{
    public function __construct(
        public ?Task $task = null,
        public ?Message $message = null,
        public ?TaskStatusUpdateEvent $statusUpdate = null,
        public ?TaskArtifactUpdateEvent $artifactUpdate = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $task = Payload::object($data, 'task');
        $message = Payload::object($data, 'message');
        $statusUpdate = Payload::object($data, 'statusUpdate');
        $artifactUpdate = Payload::object($data, 'artifactUpdate');

        return new self(
            task: $task === null ? null : Task::fromArray($task),
            message: $message === null ? null : Message::fromArray($message),
            statusUpdate: $statusUpdate === null ? null : TaskStatusUpdateEvent::fromArray($statusUpdate),
            artifactUpdate: $artifactUpdate === null ? null : TaskArtifactUpdateEvent::fromArray($artifactUpdate),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = Payload::omitNulls([
            'task' => $this->task?->toArray(),
            'message' => $this->message?->toArray(),
            'statusUpdate' => $this->statusUpdate?->toArray(),
            'artifactUpdate' => $this->artifactUpdate?->toArray(),
        ]);

        if ($payload === []) {
            throw InvalidParamsException::forField('payload', 'A stream response must contain a payload.');
        }

        return $payload;
    }

    public static function fromEvent(object $event): self
    {
        return match (true) {
            $event instanceof Task => new self(task: $event),
            $event instanceof Message => new self(message: $event),
            $event instanceof TaskStatusUpdateEvent => new self(statusUpdate: $event),
            $event instanceof TaskArtifactUpdateEvent => new self(artifactUpdate: $event),
            default => throw InvalidParamsException::forField('payload', 'Unsupported stream event.'),
        };
    }
}
