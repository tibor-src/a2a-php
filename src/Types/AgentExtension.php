<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class AgentExtension
{
    /**
     * @param  array<string, mixed>|null  $params
     */
    public function __construct(
        public string $uri,
        public ?string $description = null,
        public bool $required = false,
        public ?array $params = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uri: Payload::requireString($data, 'uri'),
            description: Payload::string($data, 'description'),
            required: Payload::bool($data, 'required') ?? false,
            params: Payload::object($data, 'params'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'uri' => $this->uri,
            'description' => $this->description,
            'required' => $this->required ? true : null,
            'params' => $this->params,
        ]);
    }
}
