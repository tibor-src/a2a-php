<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class ImplicitOAuthFlow
{
    /**
     * @param  array<string, string>  $scopes
     */
    public function __construct(
        public string $authorizationUrl,
        public array $scopes = [],
        public ?string $refreshUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            authorizationUrl: Payload::requireString($data, 'authorizationUrl'),
            scopes: AuthorizationCodeOAuthFlow::scopes($data),
            refreshUrl: Payload::string($data, 'refreshUrl'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'authorizationUrl' => $this->authorizationUrl,
            'refreshUrl' => $this->refreshUrl,
            'scopes' => $this->scopes,
        ]);
    }
}
