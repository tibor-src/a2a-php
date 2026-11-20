<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Json\Payload;

final readonly class Message
{
    /**
     * @param  list<Part>  $parts
     * @param  array<string, mixed>|null  $metadata
     * @param  list<string>  $extensions
     * @param  list<string>  $referenceTaskIds
     */
    public function __construct(
        public string $messageId,
        public Role $role,
        public array $parts,
        public ?string $contextId = null,
        public ?string $taskId = null,
        public ?array $metadata = null,
        public array $extensions = [],
        public array $referenceTaskIds = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $parts = Payload::list($data, 'parts') ?? [];

        if ($parts === []) {
            throw InvalidParamsException::forField('parts', 'At least one part is required.');
        }

        $role = Payload::requireString($data, 'role');
        $roleEnum = Role::tryFrom($role);

        if (! $roleEnum instanceof Role) {
            throw InvalidParamsException::forField('role', "Unknown role: {$role}");
        }

        return new self(
            messageId: Payload::requireString($data, 'messageId'),
            role: $roleEnum,
            parts: Payload::mapObjects($parts, Part::fromArray(...), 'parts'),
            contextId: Payload::string($data, 'contextId'),
            taskId: Payload::string($data, 'taskId'),
            metadata: Payload::object($data, 'metadata'),
            extensions: Payload::strings($data, 'extensions'),
            referenceTaskIds: Payload::strings($data, 'referenceTaskIds'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'messageId' => $this->messageId,
            'contextId' => $this->contextId,
            'taskId' => $this->taskId,
            'role' => $this->role->value,
            'parts' => array_map(fn (Part $part): array => $part->toArray(), $this->parts),
            'metadata' => $this->metadata,
            'extensions' => $this->extensions === [] ? null : $this->extensions,
            'referenceTaskIds' => $this->referenceTaskIds === [] ? null : $this->referenceTaskIds,
        ]);
    }

    public function withContext(?string $contextId, ?string $taskId): self
    {
        return new self(
            messageId: $this->messageId,
            role: $this->role,
            parts: $this->parts,
            contextId: $contextId ?? $this->contextId,
            taskId: $taskId ?? $this->taskId,
            metadata: $this->metadata,
            extensions: $this->extensions,
            referenceTaskIds: $this->referenceTaskIds,
        );
    }
}
