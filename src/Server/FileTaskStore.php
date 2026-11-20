<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

use TiborSrc\A2A\Types\Artifact;
use TiborSrc\A2A\Types\ListTasksRequest;
use TiborSrc\A2A\Types\Message;
use TiborSrc\A2A\Types\Task;
use TiborSrc\A2A\Types\TaskPushNotificationConfig;
use TiborSrc\A2A\Types\TaskState;

final readonly class FileTaskStore implements TaskStore
{
    public function __construct(
        private string $path,
    ) {}

    public function save(Task $task): void
    {
        $this->mutate(function (array $state) use ($task): array {
            $payload = $task->toArray();
            $payload['history'] = array_map(
                fn (Message $message): array => $message->toArray(),
                $task->history,
            );
            $payload['artifacts'] = array_map(
                fn (Artifact $artifact): array => $artifact->toArray(),
                $task->artifacts,
            );
            $state['tasks'][$task->id] = $payload;

            return $state;
        });
    }

    public function find(string $id): ?Task
    {
        $payload = $this->read()['tasks'][$id] ?? null;

        if (! is_array($payload)) {
            return null;
        }

        return Task::fromArray($this->stringKeyed($payload));
    }

    public function all(): array
    {
        return $this->decodeTasks($this->read()['tasks']);
    }

    public function list(ListTasksRequest $request): array
    {
        return array_values(array_filter($this->all(), function (Task $task) use ($request): bool {
            if ($request->contextId !== null && $task->contextId !== $request->contextId) {
                return false;
            }

            if ($request->status instanceof TaskState && $task->status->state !== $request->status) {
                return false;
            }

            if ($request->statusTimestampAfter !== null) {
                return ($task->status->timestamp ?? '') >= $request->statusTimestampAfter;
            }

            return true;
        }));
    }

    public function savePushConfig(TaskPushNotificationConfig $config): void
    {
        $this->mutate(function (array $state) use ($config): array {
            $taskId = $config->taskId ?? '';
            $id = $config->id ?? '';
            $bucket = $state['push'][$taskId] ?? [];

            if (! is_array($bucket)) {
                $bucket = [];
            }

            $bucket[$id] = $config->toArray();
            $state['push'][$taskId] = $bucket;

            return $state;
        });
    }

    public function findPushConfig(string $taskId, string $id): ?TaskPushNotificationConfig
    {
        $bucket = $this->read()['push'][$taskId] ?? null;

        if (! is_array($bucket)) {
            return null;
        }

        $payload = $bucket[$id] ?? null;

        if (! is_array($payload)) {
            return null;
        }

        return TaskPushNotificationConfig::fromArray($this->stringKeyed($payload));
    }

    public function listPushConfigs(string $taskId): array
    {
        $configs = $this->read()['push'][$taskId] ?? [];

        if (! is_array($configs)) {
            return [];
        }

        $out = [];

        foreach ($configs as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $out[] = TaskPushNotificationConfig::fromArray($this->stringKeyed($payload));
        }

        return $out;
    }

    public function deletePushConfig(string $taskId, string $id): void
    {
        $this->mutate(function (array $state) use ($taskId, $id): array {
            $bucket = $state['push'][$taskId] ?? null;

            if (is_array($bucket)) {
                unset($bucket[$id]);
                $state['push'][$taskId] = $bucket;
            }

            return $state;
        });
    }

    /**
     * @param  callable(array{tasks: array<string, mixed>, push: array<string, mixed>}): array{tasks: array<string, mixed>, push: array<string, mixed>}  $callback
     */
    private function mutate(callable $callback): void
    {
        $handle = fopen($this->path, 'c+');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open task store: {$this->path}");
        }

        try {
            flock($handle, LOCK_EX);
            $contents = stream_get_contents($handle);
            $state = $this->decodeState(
                is_string($contents) && $contents !== ''
                    ? json_decode($contents, true)
                    : null,
            );
            $state = $callback($state);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array{tasks: array<string, mixed>, push: array<string, mixed>}
     */
    private function read(): array
    {
        if (! is_file($this->path)) {
            return $this->emptyState();
        }

        $handle = fopen($this->path, 'r');

        if ($handle === false) {
            return $this->emptyState();
        }

        try {
            flock($handle, LOCK_EX);
            $contents = stream_get_contents($handle);

            return $this->decodeState(
                is_string($contents) && $contents !== ''
                    ? json_decode($contents, true)
                    : null,
            );
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return list<Task>
     */
    private function decodeTasks(mixed $tasks): array
    {
        if (! is_array($tasks)) {
            return [];
        }

        $out = [];

        foreach ($tasks as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $out[] = Task::fromArray($this->stringKeyed($payload));
        }

        return $out;
    }

    /**
     * @return array{tasks: array<string, mixed>, push: array<string, mixed>}
     */
    private function decodeState(mixed $state): array
    {
        if (! is_array($state)) {
            return $this->emptyState();
        }

        $tasks = $state['tasks'] ?? [];
        $push = $state['push'] ?? [];

        return [
            'tasks' => is_array($tasks) ? $this->stringKeyed($tasks) : [],
            'push' => is_array($push) ? $this->stringKeyed($push) : [],
        ];
    }

    /**
     * @return array{tasks: array<string, mixed>, push: array<string, mixed>}
     */
    private function emptyState(): array
    {
        return ['tasks' => [], 'push' => []];
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    private function stringKeyed(array $value): array
    {
        $out = [];

        foreach ($value as $key => $item) {
            $out[(string) $key] = $item;
        }

        return $out;
    }
}
