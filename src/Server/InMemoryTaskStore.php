<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

use TiborSrc\A2A\Types\ListTasksRequest;
use TiborSrc\A2A\Types\Task;
use TiborSrc\A2A\Types\TaskPushNotificationConfig;
use TiborSrc\A2A\Types\TaskState;

final class InMemoryTaskStore implements TaskStore
{
    /**
     * @var array<string, Task>
     */
    private array $tasks = [];

    /**
     * @var array<string, array<string, TaskPushNotificationConfig>>
     */
    private array $push = [];

    public function save(Task $task): void
    {
        $this->tasks[$task->id] = $task;
    }

    public function find(string $id): ?Task
    {
        return $this->tasks[$id] ?? null;
    }

    public function all(): array
    {
        return array_values($this->tasks);
    }

    public function list(ListTasksRequest $request): array
    {
        return array_values(array_filter($this->tasks, function (Task $task) use ($request): bool {
            if ($request->contextId !== null && $task->contextId !== $request->contextId) {
                return false;
            }

            if ($request->status instanceof TaskState && $task->status->state !== $request->status) {
                return false;
            }

            if ($request->statusTimestampAfter !== null) {
                $timestamp = $task->status->timestamp ?? '';

                return $timestamp >= $request->statusTimestampAfter;
            }

            return true;
        }));
    }

    public function savePushConfig(TaskPushNotificationConfig $config): void
    {
        $taskId = $config->taskId ?? '';
        $id = $config->id ?? '';
        $this->push[$taskId][$id] = $config;
    }

    public function findPushConfig(string $taskId, string $id): ?TaskPushNotificationConfig
    {
        return $this->push[$taskId][$id] ?? null;
    }

    public function listPushConfigs(string $taskId): array
    {
        return array_values($this->push[$taskId] ?? []);
    }

    public function deletePushConfig(string $taskId, string $id): void
    {
        unset($this->push[$taskId][$id]);
    }
}
