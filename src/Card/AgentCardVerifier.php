<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Card;

use TiborSrc\A2A\Json\CanonicalJson;
use TiborSrc\A2A\Types\AgentCard;

final class AgentCardVerifier
{
    public function verify(AgentCard $card, \OpenSSLAsymmetricKey $publicKey): bool
    {
        if ($card->signatures === []) {
            return false;
        }

        $payload = $card->toArray();
        unset($payload['signatures']);
        $canonical = CanonicalJson::encode($payload);

        foreach ($card->signatures as $signature) {
            $input = $signature->protected.'.'.AgentCardSigner::base64Url($canonical);
            $raw = $this->decode($signature->signature);

            if (openssl_verify($input, $raw, $publicKey, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        return false;
    }

    private function decode(string $value): string
    {
        $remainder = strlen($value) % 4;
        $padded = $value.($remainder === 0 ? '' : str_repeat('=', 4 - $remainder));
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
