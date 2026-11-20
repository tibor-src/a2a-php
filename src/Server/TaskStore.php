<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

use TiborSrc\A2A\Types\ListTasksRequest;
use TiborSrc\A2A\Types\Task;
use TiborSrc\A2A\Types\TaskPushNotificationConfig;

interface TaskStore
{
    public function save(Task $task): void;

    public function find(string $id): ?Task;

    /**
     * @return list<Task>
     */
    public function all(): array;

    /**
     * @return list<Task>
     */
    public function list(ListTasksRequest $request): array;

    public function savePushConfig(TaskPushNotificationConfig $config): void;

    public function findPushConfig(string $taskId, string $id): ?TaskPushNotificationConfig;

    /**
     * @return list<TaskPushNotificationConfig>
     */
    public function listPushConfigs(string $taskId): array;

    public function deletePushConfig(string $taskId, string $id): void;
}
