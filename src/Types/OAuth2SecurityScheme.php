<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class OAuth2SecurityScheme
{
    public function __construct(
        public OAuthFlows $flows,
        public ?string $description = null,
        public ?string $oauth2MetadataUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            flows: OAuthFlows::fromArray(Payload::requireObject($data, 'flows')),
            description: Payload::string($data, 'description'),
            oauth2MetadataUrl: Payload::string($data, 'oauth2MetadataUrl'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'description' => $this->description,
            'flows' => $this->flows->toArray(),
            'oauth2MetadataUrl' => $this->oauth2MetadataUrl,
        ]);
    }
}
