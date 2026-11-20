<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class ListTaskPushNotificationConfigsResponse
{
    /**
     * @param  list<TaskPushNotificationConfig>  $configs
     */
    public function __construct(
        public array $configs,
        public string $nextPageToken = '',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $configs = Payload::list($data, 'configs') ?? [];

        return new self(
            configs: Payload::mapObjects($configs, TaskPushNotificationConfig::fromArray(...), 'configs'),
            nextPageToken: Payload::string($data, 'nextPageToken') ?? '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'configs' => array_map(
                fn (TaskPushNotificationConfig $config): array => $config->toArray(),
                $this->configs,
            ),
            'nextPageToken' => $this->nextPageToken,
        ];
    }
}
