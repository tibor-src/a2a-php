<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class DeviceCodeOAuthFlow
{
    /**
     * @param  array<string, string>  $scopes
     */
    public function __construct(
        public string $deviceAuthorizationUrl,
        public string $tokenUrl,
        public array $scopes,
        public ?string $refreshUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            deviceAuthorizationUrl: Payload::requireString($data, 'deviceAuthorizationUrl'),
            tokenUrl: Payload::requireString($data, 'tokenUrl'),
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
            'deviceAuthorizationUrl' => $this->deviceAuthorizationUrl,
            'tokenUrl' => $this->tokenUrl,
            'refreshUrl' => $this->refreshUrl,
            'scopes' => $this->scopes,
        ]);
    }
}
