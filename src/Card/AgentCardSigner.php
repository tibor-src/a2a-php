<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Card;

use TiborSrc\A2A\Json\CanonicalJson;
use TiborSrc\A2A\Types\AgentCard;
use TiborSrc\A2A\Types\AgentCardSignature;

final class AgentCardSigner
{
    /**
     * @param  array<string, mixed>  $protectedHeader
     */
    public function sign(AgentCard $card, \OpenSSLAsymmetricKey $privateKey, array $protectedHeader): AgentCardSignature
    {
        $payload = $card->toArray();
        unset($payload['signatures']);
        $canonical = CanonicalJson::encode($payload);
        $protected = self::base64Url(json_encode($protectedHeader, JSON_THROW_ON_ERROR));
        $input = $protected.'.'.self::base64Url($canonical);

        $signature = '';
        openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! is_string($signature) || $signature === '') {
            throw new \RuntimeException('Unable to sign the agent card.');
        }

        return new AgentCardSignature($protected, self::base64Url($signature));
    }

    public static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
