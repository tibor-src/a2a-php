<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Json\Payload;

final readonly class TaskStatus
{
    public function __construct(
        public TaskState $state,
        public ?Message $message = null,
        public ?string $timestamp = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $state = Payload::requireString($data, 'state');
        $enum = TaskState::tryFrom($state);

        if (! $enum instanceof TaskState) {
            throw InvalidParamsException::forField('state', "Unknown task state: {$state}");
        }

        $message = Payload::object($data, 'message');

        return new self(
            state: $enum,
            message: $message === null ? null : Message::fromArray($message),
            timestamp: Payload::string($data, 'timestamp'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Payload::omitNulls([
            'state' => $this->state->value,
            'message' => $this->message?->toArray(),
            'timestamp' => $this->timestamp,
        ]);
    }

    public static function now(TaskState $state, ?Message $message = null): self
    {
        return new self($state, $message, gmdate('Y-m-d\TH:i:s.v\Z'));
    }
}
