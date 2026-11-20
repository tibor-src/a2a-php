<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class SendMessageConfiguration
{
    /**
     * @param  list<string>  $acceptedOutputModes
     */
    public function __construct(
        public array $acceptedOutputModes = [],
        public ?TaskPushNotificationConfig $taskPushNotificationConfig = null,
        public ?int $historyLength = null,
        public bool $returnImmediately = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $config = Payload::object($data, 'taskPushNotificationConfig');

        return new self(
            acceptedOutputModes: Payload::optionalStrings($data, 'acceptedOutputModes'),
            taskPushNotificationConfig: $config === null ? null : TaskPushNotificationConfig::fromArray($config),
            historyLength: Payload::int($data, 'historyLength'),
            returnImmediately: Payload::bool($data, 'returnImmediately') ?? false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'acceptedOutputModes' => $this->acceptedOutputModes === [] ? null : $this->acceptedOutputModes,
            'taskPushNotificationConfig' => $this->taskPushNotificationConfig?->toArray(),
            'historyLength' => $this->historyLength,
            'returnImmediately' => $this->returnImmediately ? true : null,
        ]);
    }
}
