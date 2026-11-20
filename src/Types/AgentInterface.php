<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class AgentInterface
{
    public function __construct(
        public string $url,
        public string $protocolBinding,
        public string $protocolVersion,
        public ?string $tenant = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: Payload::requireString($data, 'url'),
            protocolBinding: Payload::requireString($data, 'protocolBinding'),
            protocolVersion: Payload::requireString($data, 'protocolVersion'),
            tenant: Payload::string($data, 'tenant'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'url' => $this->url,
            'protocolBinding' => $this->protocolBinding,
            'protocolVersion' => $this->protocolVersion,
            'tenant' => $this->tenant,
        ]);
    }
}
