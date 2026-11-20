<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class AuthorizationCodeOAuthFlow
{
    /**
     * @param  array<string, string>  $scopes
     */
    public function __construct(
        public string $authorizationUrl,
        public string $tokenUrl,
        public array $scopes,
        public ?string $refreshUrl = null,
        public bool $pkceRequired = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            authorizationUrl: Payload::requireString($data, 'authorizationUrl'),
            tokenUrl: Payload::requireString($data, 'tokenUrl'),
            scopes: self::scopes($data),
            refreshUrl: Payload::string($data, 'refreshUrl'),
            pkceRequired: Payload::bool($data, 'pkceRequired') ?? false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'authorizationUrl' => $this->authorizationUrl,
            'tokenUrl' => $this->tokenUrl,
            'refreshUrl' => $this->refreshUrl,
            'scopes' => $this->scopes,
            'pkceRequired' => $this->pkceRequired ? true : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function scopes(array $data): array
    {
        $scopes = Payload::object($data, 'scopes') ?? [];
        $mapped = [];

        foreach ($scopes as $name => $description) {
            $mapped[$name] = is_string($description) ? $description : '';
        }

        return $mapped;
    }
}
