<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Json\Payload;

final readonly class Artifact
{
    /**
     * @param  list<Part>  $parts
     * @param  array<string, mixed>|null  $metadata
     * @param  list<string>  $extensions
     */
    public function __construct(
        public string $artifactId,
        public array $parts,
        public ?string $name = null,
        public ?string $description = null,
        public ?array $metadata = null,
        public array $extensions = [],
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

        return new self(
            artifactId: Payload::requireString($data, 'artifactId'),
            parts: Payload::mapObjects($parts, Part::fromArray(...), 'parts'),
            name: Payload::string($data, 'name'),
            description: Payload::string($data, 'description'),
            metadata: Payload::object($data, 'metadata'),
            extensions: Payload::optionalStrings($data, 'extensions'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'artifactId' => $this->artifactId,
            'name' => $this->name,
            'description' => $this->description,
            'parts' => array_map(fn (Part $part): array => $part->toArray(), $this->parts),
            'metadata' => $this->metadata,
            'extensions' => $this->extensions === [] ? null : $this->extensions,
        ]);
    }
}
