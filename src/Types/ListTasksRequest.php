<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Json\Payload;

final readonly class ListTasksRequest
{
    public function __construct(
        public ?string $tenant = null,
        public ?string $contextId = null,
        public ?TaskState $status = null,
        public ?int $pageSize = null,
        public ?string $pageToken = null,
        public ?int $historyLength = null,
        public ?string $statusTimestampAfter = null,
        public ?bool $includeArtifacts = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $status = Payload::string($data, 'status');
        $enum = $status === null ? null : TaskState::tryFrom($status);

        if ($status !== null && ! $enum instanceof TaskState) {
            throw InvalidParamsException::forField('status', "Unknown task state: {$status}");
        }

        return new self(
            tenant: Payload::string($data, 'tenant'),
            contextId: Payload::string($data, 'contextId'),
            status: $enum,
            pageSize: Payload::int($data, 'pageSize'),
            pageToken: Payload::string($data, 'pageToken'),
            historyLength: Payload::int($data, 'historyLength'),
            statusTimestampAfter: Payload::string($data, 'statusTimestampAfter'),
            includeArtifacts: Payload::bool($data, 'includeArtifacts'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'tenant' => $this->tenant,
            'contextId' => $this->contextId,
            'status' => $this->status?->value,
            'pageSize' => $this->pageSize,
            'pageToken' => $this->pageToken,
            'historyLength' => $this->historyLength,
            'statusTimestampAfter' => $this->statusTimestampAfter,
            'includeArtifacts' => $this->includeArtifacts,
        ]);
    }
}
