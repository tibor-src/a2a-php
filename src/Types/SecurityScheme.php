<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Json\Payload;

final readonly class SecurityScheme
{
    public function __construct(
        public ?APIKeySecurityScheme $apiKeySecurityScheme = null,
        public ?HTTPAuthSecurityScheme $httpAuthSecurityScheme = null,
        public ?OAuth2SecurityScheme $oauth2SecurityScheme = null,
        public ?OpenIdConnectSecurityScheme $openIdConnectSecurityScheme = null,
        public ?MutualTlsSecurityScheme $mtlsSecurityScheme = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $apiKey = Payload::object($data, 'apiKeySecurityScheme');
        $http = Payload::object($data, 'httpAuthSecurityScheme');
        $oauth2 = Payload::object($data, 'oauth2SecurityScheme');
        $oidc = Payload::object($data, 'openIdConnectSecurityScheme');
        $mtls = Payload::object($data, 'mtlsSecurityScheme');

        $scheme = new self(
            apiKeySecurityScheme: $apiKey === null ? null : APIKeySecurityScheme::fromArray($apiKey),
            httpAuthSecurityScheme: $http === null ? null : HTTPAuthSecurityScheme::fromArray($http),
            oauth2SecurityScheme: $oauth2 === null ? null : OAuth2SecurityScheme::fromArray($oauth2),
            openIdConnectSecurityScheme: $oidc === null ? null : OpenIdConnectSecurityScheme::fromArray($oidc),
            mtlsSecurityScheme: $mtls === null ? null : MutualTlsSecurityScheme::fromArray($mtls),
        );

        if ($scheme->toArray() === []) {
            throw InvalidParamsException::forField('securityScheme', 'A security scheme is required.');
        }

        return $scheme;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'apiKeySecurityScheme' => $this->apiKeySecurityScheme?->toArray(),
            'httpAuthSecurityScheme' => $this->httpAuthSecurityScheme?->toArray(),
            'oauth2SecurityScheme' => $this->oauth2SecurityScheme?->toArray(),
            'openIdConnectSecurityScheme' => $this->openIdConnectSecurityScheme?->toArray(),
            'mtlsSecurityScheme' => $this->mtlsSecurityScheme?->toArray(),
        ]);
    }
}
