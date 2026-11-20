<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class Task
{
    /**
     * @param  list<Artifact>  $artifacts
     * @param  list<Message>  $history
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public TaskStatus $status,
        public ?string $contextId = null,
        public array $artifacts = [],
        public array $history = [],
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $artifacts = Payload::list($data, 'artifacts') ?? [];
        $history = Payload::list($data, 'history') ?? [];

        return new self(
            id: Payload::requireString($data, 'id'),
            status: TaskStatus::fromArray(Payload::requireObject($data, 'status')),
            contextId: Payload::string($data, 'contextId'),
            artifacts: Payload::mapObjects($artifacts, Artifact::fromArray(...), 'artifacts'),
            history: Payload::mapObjects($history, Message::fromArray(...), 'history'),
            metadata: Payload::object($data, 'metadata'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'id' => $this->id,
            'contextId' => $this->contextId,
            'status' => $this->status->toArray(),
            'artifacts' => $this->artifacts === [] ? null : array_map(
                fn (Artifact $artifact): array => $artifact->toArray(),
                $this->artifacts,
            ),
            'history' => $this->history === [] ? null : array_map(
                fn (Message $message): array => $message->toArray(),
                $this->history,
            ),
            'metadata' => $this->metadata,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArrayWithHistory(?int $historyLength, bool $includeArtifacts = true): array
    {
        $task = $this;

        if ($historyLength !== null) {
            $history = $historyLength <= 0
                ? []
                : array_slice($this->history, -$historyLength);
            $task = new self(
                id: $this->id,
                status: $this->status,
                contextId: $this->contextId,
                artifacts: $this->artifacts,
                history: $history,
                metadata: $this->metadata,
            );
        }

        $payload = $task->toArray();

        if (! $includeArtifacts) {
            unset($payload['artifacts']);
        }

        return $payload;
    }

    public function withStatus(TaskStatus $status): self
    {
        return new self($this->id, $status, $this->contextId, $this->artifacts, $this->history, $this->metadata);
    }

    public function withMessage(Message $message): self
    {
        return new self(
            $this->id,
            $this->status,
            $this->contextId,
            $this->artifacts,
            [...$this->history, $message],
            $this->metadata,
        );
    }

    public function withArtifact(Artifact $artifact, bool $append = false): self
    {
        $artifacts = $this->artifacts;

        if ($append) {
            foreach ($artifacts as $index => $existing) {
                if ($existing->artifactId === $artifact->artifactId) {
                    $artifacts[$index] = new Artifact(
                        artifactId: $existing->artifactId,
                        parts: [...$existing->parts, ...$artifact->parts],
                        name: $artifact->name ?? $existing->name,
                        description: $artifact->description ?? $existing->description,
                        metadata: $artifact->metadata ?? $existing->metadata,
                        extensions: $artifact->extensions === [] ? $existing->extensions : $artifact->extensions,
                    );

                    return new self($this->id, $this->status, $this->contextId, $artifacts, $this->history, $this->metadata);
                }
            }
        }

        $artifacts[] = $artifact;

        return new self($this->id, $this->status, $this->contextId, $artifacts, $this->history, $this->metadata);
    }
}
