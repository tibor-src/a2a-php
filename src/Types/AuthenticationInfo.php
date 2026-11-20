<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class AuthenticationInfo
{
    public function __construct(
        public string $scheme,
        public ?string $credentials = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scheme: Payload::requireString($data, 'scheme'),
            credentials: Payload::string($data, 'credentials'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'scheme' => $this->scheme,
            'credentials' => $this->credentials,
        ]);
    }
}
