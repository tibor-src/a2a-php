<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Json\Payload;

final readonly class SendMessageResponse
{
    public function __construct(
        public ?Task $task = null,
        public ?Message $message = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $task = Payload::object($data, 'task');
        $message = Payload::object($data, 'message');

        return new self(
            task: $task === null ? null : Task::fromArray($task),
            message: $message === null ? null : Message::fromArray($message),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = Payload::omitNulls([
            'task' => $this->task?->toArray(),
            'message' => $this->message?->toArray(),
        ]);

        if ($payload === []) {
            throw InvalidParamsException::forField('payload', 'SendMessage must return a task or a message.');
        }

        return $payload;
    }
}
