<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class TaskStatusUpdateEvent
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $taskId,
        public string $contextId,
        public TaskStatus $status,
        public ?array $metadata = null,
        public bool $final = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            taskId: Payload::requireString($data, 'taskId'),
            contextId: Payload::requireString($data, 'contextId'),
            status: TaskStatus::fromArray(Payload::requireObject($data, 'status')),
            metadata: Payload::object($data, 'metadata'),
            final: Payload::bool($data, 'final') ?? false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'taskId' => $this->taskId,
            'contextId' => $this->contextId,
            'status' => $this->status->toArray(),
            'metadata' => $this->metadata,
        ]);
    }
}
