<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class OAuthFlows
{
    public function __construct(
        public ?AuthorizationCodeOAuthFlow $authorizationCode = null,
        public ?ClientCredentialsOAuthFlow $clientCredentials = null,
        public ?ImplicitOAuthFlow $implicit = null,
        public ?PasswordOAuthFlow $password = null,
        public ?DeviceCodeOAuthFlow $deviceCode = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $authorizationCode = Payload::object($data, 'authorizationCode');
        $clientCredentials = Payload::object($data, 'clientCredentials');
        $implicit = Payload::object($data, 'implicit');
        $password = Payload::object($data, 'password');
        $deviceCode = Payload::object($data, 'deviceCode');

        return new self(
            authorizationCode: $authorizationCode === null ? null : AuthorizationCodeOAuthFlow::fromArray($authorizationCode),
            clientCredentials: $clientCredentials === null ? null : ClientCredentialsOAuthFlow::fromArray($clientCredentials),
            implicit: $implicit === null ? null : ImplicitOAuthFlow::fromArray($implicit),
            password: $password === null ? null : PasswordOAuthFlow::fromArray($password),
            deviceCode: $deviceCode === null ? null : DeviceCodeOAuthFlow::fromArray($deviceCode),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'authorizationCode' => $this->authorizationCode?->toArray(),
            'clientCredentials' => $this->clientCredentials?->toArray(),
            'implicit' => $this->implicit?->toArray(),
            'password' => $this->password?->toArray(),
            'deviceCode' => $this->deviceCode?->toArray(),
        ]);
    }
}
