<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class TaskArtifactUpdateEvent
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $taskId,
        public string $contextId,
        public Artifact $artifact,
        public ?bool $append = null,
        public ?bool $lastChunk = null,
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            taskId: Payload::requireString($data, 'taskId'),
            contextId: Payload::requireString($data, 'contextId'),
            artifact: Artifact::fromArray(Payload::requireObject($data, 'artifact')),
            append: Payload::bool($data, 'append'),
            lastChunk: Payload::bool($data, 'lastChunk'),
            metadata: Payload::object($data, 'metadata'),
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
            'artifact' => $this->artifact->toArray(),
            'append' => $this->append,
            'lastChunk' => $this->lastChunk,
            'metadata' => $this->metadata,
        ]);
    }
}
