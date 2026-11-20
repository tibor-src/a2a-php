<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class AgentCardSignature
{
    /**
     * @param  array<string, mixed>|null  $header
     */
    public function __construct(
        public string $protected,
        public string $signature,
        public ?array $header = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            protected: Payload::requireString($data, 'protected'),
            signature: Payload::requireString($data, 'signature'),
            header: Payload::object($data, 'header'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'protected' => $this->protected,
            'signature' => $this->signature,
            'header' => $this->header,
        ]);
    }
}
