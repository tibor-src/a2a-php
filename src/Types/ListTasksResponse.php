<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

use TiborSrc\A2A\Json\Payload;

final readonly class ListTasksResponse
{
    /**
     * @param  list<Task>  $tasks
     */
    public function __construct(
        public array $tasks,
        public string $nextPageToken,
        public int $pageSize,
        public int $totalSize,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $tasks = Payload::list($data, 'tasks') ?? [];

        return new self(
            tasks: Payload::mapObjects($tasks, Task::fromArray(...), 'tasks'),
            nextPageToken: Payload::string($data, 'nextPageToken') ?? '',
            pageSize: Payload::int($data, 'pageSize') ?? count($tasks),
            totalSize: Payload::int($data, 'totalSize') ?? count($tasks),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tasks' => array_map(fn (Task $task): array => $task->toArray(), $this->tasks),
            'nextPageToken' => $this->nextPageToken,
            'pageSize' => $this->pageSize,
            'totalSize' => $this->totalSize,
        ];
    }
}
