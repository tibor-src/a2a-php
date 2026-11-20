<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class AgentCapabilities
{
    /**
     * @param  list<AgentExtension>  $extensions
     */
    public function __construct(
        public ?bool $streaming = null,
        public ?bool $pushNotifications = null,
        public array $extensions = [],
        public ?bool $extendedAgentCard = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $extensions = Payload::list($data, 'extensions') ?? [];

        return new self(
            streaming: Payload::bool($data, 'streaming'),
            pushNotifications: Payload::bool($data, 'pushNotifications'),
            extensions: Payload::mapObjects($extensions, AgentExtension::fromArray(...), 'extensions'),
            extendedAgentCard: Payload::bool($data, 'extendedAgentCard'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'streaming' => $this->streaming,
            'pushNotifications' => $this->pushNotifications,
            'extensions' => $this->extensions === [] ? null : array_map(
                fn (AgentExtension $extension): array => $extension->toArray(),
                $this->extensions,
            ),
            'extendedAgentCard' => $this->extendedAgentCard,
        ]);
    }
}
