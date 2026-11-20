<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class TaskPushNotificationConfig
{
    public function __construct(
        public string $url,
        public ?string $tenant = null,
        public ?string $id = null,
        public ?string $taskId = null,
        public ?string $token = null,
        public ?AuthenticationInfo $authentication = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $nested = Payload::object($data, 'config');

        if ($nested !== null) {
            $data = [...$nested, ...$data];
        }

        $authentication = Payload::object($data, 'authentication');

        return new self(
            url: Payload::requireString($data, 'url'),
            tenant: Payload::string($data, 'tenant'),
            id: Payload::string($data, 'id'),
            taskId: Payload::string($data, 'taskId'),
            token: Payload::string($data, 'token'),
            authentication: $authentication === null ? null : AuthenticationInfo::fromArray($authentication),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'tenant' => $this->tenant,
            'id' => $this->id,
            'taskId' => $this->taskId,
            'url' => $this->url,
            'token' => $this->token,
            'authentication' => $this->authentication?->toArray(),
        ]);
    }

    public function withIds(string $id, string $taskId): self
    {
        return new self($this->url, $this->tenant, $id, $taskId, $this->token, $this->authentication);
    }
}
