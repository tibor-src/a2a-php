<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Json;

final class CanonicalJson
{
    /**
     * RFC 8785 JSON Canonicalization Scheme for Agent Card signatures.
     */
    public static function encode(mixed $value): string
    {
        return self::encodeValue($value);
    }

    private static function encodeValue(mixed $value): string
    {
        if (is_array($value)) {
            if (self::isList($value)) {
                $items = array_map(self::encodeValue(...), $value);

                return '['.implode(',', $items).']';
            }

            $keys = array_keys($value);
            sort($keys, SORT_STRING);
            $parts = [];

            foreach ($keys as $key) {
                $parts[] = self::encodeString((string) $key).':'.self::encodeValue($value[$key]);
            }

            return '{'.implode(',', $parts).'}';
        }

        if (is_string($value)) {
            return self::encodeString($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (is_nan($value) || is_infinite($value)) {
                return 'null';
            }

            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private static function encodeString(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<mixed>  $value
     */
    private static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_is_list($value);
    }
}
