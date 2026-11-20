<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Json;

use TiborSrc\A2A\Exceptions\InvalidParamsException;

final class Payload
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function string(array $data, string $key): ?string
    {
        [$found, $value] = self::lookup($data, $key);

        if (! $found || $value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw InvalidParamsException::forField($key, 'Expected a string.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function requireString(array $data, string $key): string
    {
        $value = self::string($data, $key);

        if ($value === null || $value === '') {
            throw InvalidParamsException::forField($key, 'Required string is missing.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function int(array $data, string $key): ?int
    {
        [$found, $value] = self::lookup($data, $key);

        if (! $found || $value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw InvalidParamsException::forField($key, 'Expected an integer.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function bool(array $data, string $key): ?bool
    {
        [$found, $value] = self::lookup($data, $key);

        if (! $found || $value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 'true' || $value === '1') {
            return true;
        }

        if ($value === 'false' || $value === '0') {
            return false;
        }

        throw InvalidParamsException::forField($key, 'Expected a boolean.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, mixed>|null
     */
    public static function list(array $data, string $key): ?array
    {
        [$found, $value] = self::lookup($data, $key);

        if (! $found || $value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw InvalidParamsException::forField($key, 'Expected a list.');
        }

        return array_values($value);
    }

    /**
     * @template T of object
     *
     * @param  array<int, mixed>  $items
     * @param  callable(array<string, mixed>): T  $mapper
     * @return list<T>
     */
    public static function mapObjects(array $items, callable $mapper, string $field): array
    {
        $mapped = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw InvalidParamsException::forField($field, 'Each item must be an object.');
            }

            $object = [];

            foreach ($item as $key => $value) {
                $object[(string) $key] = $value;
            }

            $mapped[] = $mapper($object);
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        $values = self::list($data, $key) ?? [];
        $strings = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                throw InvalidParamsException::forField($key, 'Expected a list of strings.');
            }

            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function optionalStrings(array $data, string $key): array
    {
        $values = self::list($data, $key) ?? [];
        $strings = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function object(array $data, string $key): ?array
    {
        [$found, $value] = self::lookup($data, $key);

        if (! $found || $value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw InvalidParamsException::forField($key, 'Expected an object.');
        }

        /** @var array<string, mixed> $object */
        $object = $value;

        return $object;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function requireObject(array $data, string $key): array
    {
        $value = self::object($data, $key);

        if ($value === null) {
            throw InvalidParamsException::forField($key, 'Required object is missing.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function omitNulls(array $payload): array
    {
        $out = [];

        foreach ($payload as $key => $value) {
            if ($value === null) {
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: bool, 1: mixed}
     */
    private static function lookup(array $data, string $key): array
    {
        if (array_key_exists($key, $data)) {
            return [true, $data[$key]];
        }

        $snake = strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($key)));

        if ($snake !== $key && array_key_exists($snake, $data)) {
            return [true, $data[$snake]];
        }

        return [false, null];
    }

    public static function bytesToBase64(string $bytes): string
    {
        return base64_encode($bytes);
    }

    public static function base64ToBytes(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw InvalidParamsException::forField('raw', 'Invalid base64 payload.');
        }

        return $decoded;
    }
}
