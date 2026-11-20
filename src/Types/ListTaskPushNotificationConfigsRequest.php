<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class ListTaskPushNotificationConfigsRequest
{
    public function __construct(
        public string $taskId,
        public ?string $tenant = null,
        public ?int $pageSize = null,
        public ?string $pageToken = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            taskId: Payload::requireString($data, 'taskId'),
            tenant: Payload::string($data, 'tenant'),
            pageSize: Payload::int($data, 'pageSize'),
            pageToken: Payload::string($data, 'pageToken'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'tenant' => $this->tenant,
            'taskId' => $this->taskId,
            'pageSize' => $this->pageSize,
            'pageToken' => $this->pageToken,
        ]);
    }
}
