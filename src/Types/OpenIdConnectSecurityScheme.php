<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class OpenIdConnectSecurityScheme
{
    public function __construct(
        public string $openIdConnectUrl,
        public ?string $description = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            openIdConnectUrl: Payload::requireString($data, 'openIdConnectUrl'),
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
            'openIdConnectUrl' => $this->openIdConnectUrl,
        ]);
    }
}
