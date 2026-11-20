<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class SubscribeToTaskRequest
{
    public function __construct(
        public string $id,
        public ?string $tenant = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::requireString($data, 'id'),
            tenant: Payload::string($data, 'tenant'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'tenant' => $this->tenant,
            'id' => $this->id,
        ]);
    }
}
