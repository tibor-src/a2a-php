<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class APIKeySecurityScheme
{
    public function __construct(
        public string $location,
        public string $name,
        public ?string $description = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            location: Payload::requireString($data, 'location'),
            name: Payload::requireString($data, 'name'),
            description: Payload::string($data, 'description'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'description' => $this->description,
            'location' => $this->location,
            'name' => $this->name,
        ]);
    }
}
