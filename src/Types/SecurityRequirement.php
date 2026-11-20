<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Json\Payload;

final readonly class SecurityRequirement
{
    /**
     * @param  array<string, list<string>>  $schemes
     */
    public function __construct(
        public array $schemes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $schemes = Payload::object($data, 'schemes') ?? [];
        $mapped = [];

        foreach ($schemes as $name => $value) {
            if (is_array($value) && array_key_exists('list', $value) && is_array($value['list'])) {
                $mapped[$name] = array_values(array_filter($value['list'], is_string(...)));

                continue;
            }

            if (is_array($value)) {
                $mapped[$name] = array_values(array_filter($value, is_string(...)));

                continue;
            }

            throw InvalidParamsException::forField('schemes', 'Each scheme must list scopes.');
        }

        return new self($mapped);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schemes = [];

        foreach ($this->schemes as $name => $scopes) {
            $schemes[$name] = ['list' => $scopes];
        }

        return ['schemes' => $schemes];
    }
}
