<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class HTTPAuthSecurityScheme
{
    public function __construct(
        public string $scheme,
        public ?string $description = null,
        public ?string $bearerFormat = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scheme: Payload::requireString($data, 'scheme'),
            description: Payload::string($data, 'description'),
            bearerFormat: Payload::string($data, 'bearerFormat'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'description' => $this->description,
            'scheme' => $this->scheme,
            'bearerFormat' => $this->bearerFormat,
        ]);
    }
}
