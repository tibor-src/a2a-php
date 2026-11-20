<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class SendMessageRequest
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public Message $message,
        public ?string $tenant = null,
        public ?SendMessageConfiguration $configuration = null,
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $configuration = Payload::object($data, 'configuration');

        return new self(
            message: Message::fromArray(Payload::requireObject($data, 'message')),
            tenant: Payload::string($data, 'tenant'),
            configuration: $configuration === null ? null : SendMessageConfiguration::fromArray($configuration),
            metadata: Payload::object($data, 'metadata'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'tenant' => $this->tenant,
            'message' => $this->message->toArray(),
            'configuration' => $this->configuration?->toArray(),
            'metadata' => $this->metadata,
        ]);
    }
}
