<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class GetTaskRequest
{
    public function __construct(
        public string $id,
        public ?string $tenant = null,
        public ?int $historyLength = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Payload::requireString($data, 'id'),
            tenant: Payload::string($data, 'tenant'),
            historyLength: Payload::int($data, 'historyLength'),
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
            'historyLength' => $this->historyLength,
        ]);
    }
}
