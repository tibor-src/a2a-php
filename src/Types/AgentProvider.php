<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class AgentProvider
{
    public function __construct(
        public string $url,
        public string $organization,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: Payload::requireString($data, 'url'),
            organization: Payload::requireString($data, 'organization'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'organization' => $this->organization,
        ];
    }
}
