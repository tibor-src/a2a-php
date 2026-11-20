<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

use TiborSrc\A2A\Exceptions\ContentTypeNotSupportedException;
use TiborSrc\A2A\Exceptions\ExtendedAgentCardNotConfiguredException;
use TiborSrc\A2A\Exceptions\ExtensionSupportRequiredException;
use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Exceptions\PushNotificationNotSupportedException;
use TiborSrc\A2A\Exceptions\TaskNotCancelableException;
use TiborSrc\A2A\Exceptions\TaskNotFoundException;
use TiborSrc\A2A\Exceptions\UnsupportedOperationException;
use TiborSrc\A2A\Exceptions\VersionNotSupportedException;
use TiborSrc\A2A\Id;
use TiborSrc\A2A\Protocol;
use TiborSrc\A2A\Types\AgentCard;
use TiborSrc\A2A\Types\CancelTaskRequest;
use TiborSrc\A2A\Types\DeleteTaskPushNotificationConfigRequest;
use TiborSrc\A2A\Types\GetExtendedAgentCardRequest;
use TiborSrc\A2A\Types\GetTaskPushNotificationConfigRequest;
use TiborSrc\A2A\Types\GetTaskRequest;
use TiborSrc\A2A\Types\ListTaskPushNotificationConfigsRequest;
use TiborSrc\A2A\Types\ListTaskPushNotificationConfigsResponse;
use TiborSrc\A2A\Types\ListTasksRequest;
use TiborSrc\A2A\Types\ListTasksResponse;
use TiborSrc\A2A\Types\Message;
use TiborSrc\A2A\Types\Part;
use TiborSrc\A2A\Types\Role;
use TiborSrc\A2A\Types\SendMessageRequest;
use TiborSrc\A2A\Types\SendMessageResponse;
use TiborSrc\A2A\Types\StreamResponse;
use TiborSrc\A2A\Types\SubscribeToTaskRequest;
use TiborSrc\A2A\Types\Task;
use TiborSrc\A2A\Types\TaskArtifactUpdateEvent;
use TiborSrc\A2A\Types\TaskPushNotificationConfig;
use TiborSrc\A2A\Types\TaskState;
use TiborSrc\A2A\Types\TaskStatus;
use TiborSrc\A2A\Types\TaskStatusUpdateEvent;

final readonly class RequestHandler
{
    public function __construct(
        private AgentCard $agentCard,
        private AgentExecutor $executor,
        private TaskStore $store,
        private PushNotificationSender $pushSender = new NullPushNotificationSender,
        private ?AgentCard $extendedAgentCard = null,
        private int $subscribeTimeoutSeconds = 15,
    ) {}

    public function agentCard(): AgentCard
    {
        return $this->agentCard;
    }

    public function assertVersion(?string $version): void
    {
        $requested = $version === null || $version === '' ? '0.3' : $version;

        if (! in_array($requested, Protocol::supportedVersions(), true)) {
            throw new VersionNotSupportedException($requested);
        }
    }

    /**
     * @param  list<string>  $extensions
     */
    public function assertExtensions(array $extensions): void
    {
        $supported = [];

        foreach ($this->agentCard->capabilities->extensions as $extension) {
            $supported[$extension->uri] = $extension;
        }

        foreach ($this->agentCard->capabilities->extensions as $extension) {
            if ($extension->required && ! in_array($extension->uri, $extensions, true)) {
                throw new ExtensionSupportRequiredException($extension->uri);
            }
        }
    }

    public function sendMessage(SendMessageRequest $request, bool $streaming = false): SendMessageResponse
    {
        $this->assertStreaming($streaming);
        $this->validateMessage($request->message);

        $queue = new EventQueue;
        $existing = $this->resolveExistingTask($request->message);
        $task = $existing ?? $this->createTask($request->message);

        $message = $request->message->withContext($task->contextId, $task->id);
        $task = $task->withMessage($message)->withStatus(TaskStatus::now(TaskState::Submitted));
        $this->store->save($task);

        $this->maybeSavePushConfig($request, $task);

        $this->executor->execute(
            new RequestContext($message, $task, $request->configuration, $request->metadata, $streaming),
            $queue,
        );

        $task = $this->applyEvents($task, $queue);

        foreach ($queue->events() as $event) {
            if ($event instanceof Message) {
                return new SendMessageResponse(message: $event);
            }
        }

        return new SendMessageResponse(task: $this->withHistoryLength($task, $request->configuration?->historyLength));
    }

    /**
     * @return list<StreamResponse>
     */
    public function sendStreamingMessage(SendMessageRequest $request): array
    {
        $this->assertStreaming(true);
        $this->validateMessage($request->message);

        $queue = new EventQueue;
        $existing = $this->resolveExistingTask($request->message);
        $task = $existing ?? $this->createTask($request->message);
        $message = $request->message->withContext($task->contextId, $task->id);
        $task = $task->withMessage($message)->withStatus(TaskStatus::now(TaskState::Submitted));
        $this->store->save($task);
        $this->maybeSavePushConfig($request, $task);

        $this->executor->execute(
            new RequestContext($message, $task, $request->configuration, $request->metadata, true),
            $queue,
        );

        $events = $queue->events();

        if ($events === []) {
            $events = [$task];
        }

        $this->applyEvents($task, $queue);

        return array_map(StreamResponse::fromEvent(...), $events);
    }

    public function getTask(GetTaskRequest $request): Task
    {
        $task = $this->requireTask($request->id);

        return $this->withHistoryLength($task, $request->historyLength);
    }

    public function listTasks(ListTasksRequest $request): ListTasksResponse
    {
        $tasks = $this->store->list($request);
        $pageSize = $request->pageSize ?? 50;
        $pageSize = max(1, min(100, $pageSize));

        $offset = 0;

        if ($request->pageToken !== null && $request->pageToken !== '') {
            $offset = (int) $request->pageToken;
        }

        $slice = array_slice($tasks, $offset, $pageSize);
        $mapped = array_map(
            fn (Task $task): Task => $this->withHistoryLength(
                $request->includeArtifacts === false
                    ? new Task($task->id, $task->status, $task->contextId, [], $task->history, $task->metadata)
                    : $task,
                $request->historyLength,
            ),
            $slice,
        );
        $next = $offset + $pageSize < count($tasks) ? (string) ($offset + $pageSize) : '';

        return new ListTasksResponse($mapped, $next, $pageSize, count($tasks));
    }

    public function cancelTask(CancelTaskRequest $request): Task
    {
        $task = $this->requireTask($request->id);

        if ($task->status->state->isTerminal()) {
            throw new TaskNotCancelableException($task->id);
        }

        $queue = new EventQueue;
        $this->executor->cancel(new RequestContext(
            message: new Message(Id::uuid(), Role::User, [Part::text('cancel')]),
            task: $task,
        ), $queue);

        if ($queue->events() === []) {
            $queue->write(new TaskStatusUpdateEvent(
                $task->id,
                $task->contextId ?? $task->id,
                TaskStatus::now(TaskState::Canceled),
                final: true,
            ));
        }

        return $this->applyEvents($task, $queue);
    }

    /**
     * @param  (callable(StreamResponse): void)|null  $onEvent
     * @return list<StreamResponse>
     */
    public function subscribeToTask(SubscribeToTaskRequest $request, ?callable $onEvent = null): array
    {
        $this->assertStreaming(true);
        $task = $this->requireTask($request->id);

        if ($task->status->state->isTerminal()) {
            throw new UnsupportedOperationException('Cannot subscribe to a task in a terminal state.');
        }

        $deadline = time() + $this->subscribeTimeoutSeconds;
        $events = [];
        $lastState = $task->status->state;
        $push = function (StreamResponse $event) use ($onEvent, &$events): void {
            $events[] = $event;

            if ($onEvent !== null) {
                $onEvent($event);
            }
        };

        $push(StreamResponse::fromEvent($task));

        while (time() <= $deadline) {
            $current = $this->requireTask($request->id);

            if ($current->status->state !== $lastState) {
                $push(StreamResponse::fromEvent(new TaskStatusUpdateEvent(
                    $current->id,
                    $current->contextId ?? $current->id,
                    $current->status,
                    final: $current->status->state->isTerminal(),
                )));
                $lastState = $current->status->state;
            }

            if ($current->status->state->isTerminal()) {
                break;
            }

            usleep(100_000);
        }

        return $events;
    }

    public function createPushConfig(TaskPushNotificationConfig $config): TaskPushNotificationConfig
    {
        $this->assertPush();
        $taskId = $config->taskId ?? '';

        if ($taskId === '') {
            throw InvalidParamsException::forField('taskId', 'Task id is required.');
        }

        $this->requireTask($taskId);
        $saved = $config->withIds($config->id ?? Id::uuid(), $taskId);
        $this->store->savePushConfig($saved);

        return $saved;
    }

    public function getPushConfig(GetTaskPushNotificationConfigRequest $request): TaskPushNotificationConfig
    {
        $this->assertPush();
        $this->requireTask($request->taskId);
        $config = $this->store->findPushConfig($request->taskId, $request->id);

        if (! $config instanceof TaskPushNotificationConfig) {
            throw new TaskNotFoundException($request->id);
        }

        return $config;
    }

    public function listPushConfigs(ListTaskPushNotificationConfigsRequest $request): ListTaskPushNotificationConfigsResponse
    {
        $this->assertPush();
        $this->requireTask($request->taskId);

        return new ListTaskPushNotificationConfigsResponse($this->store->listPushConfigs($request->taskId));
    }

    public function deletePushConfig(DeleteTaskPushNotificationConfigRequest $request): void
    {
        $this->assertPush();
        $this->requireTask($request->taskId);
        $this->store->deletePushConfig($request->taskId, $request->id);
    }

    public function getExtendedAgentCard(GetExtendedAgentCardRequest $request): AgentCard
    {
        unset($request);

        if ($this->agentCard->capabilities->extendedAgentCard !== true) {
            throw new ExtendedAgentCardNotConfiguredException;
        }

        return $this->extendedAgentCard ?? $this->agentCard;
    }

    private function assertStreaming(bool $streaming): void
    {
        if ($streaming && $this->agentCard->capabilities->streaming !== true) {
            throw new UnsupportedOperationException('Streaming is not supported by this agent.');
        }
    }

    private function assertPush(): void
    {
        if ($this->agentCard->capabilities->pushNotifications !== true) {
            throw new PushNotificationNotSupportedException;
        }
    }

    private function validateMessage(Message $message): void
    {
        $accepted = $this->agentCard->acceptedInputModes();

        foreach ($message->parts as $part) {
            $mediaType = $part->mediaType;

            if ($mediaType === null || $mediaType === '') {
                continue;
            }

            if ($accepted !== [] && ! in_array($mediaType, $accepted, true)) {
                throw new ContentTypeNotSupportedException($mediaType);
            }
        }
    }

    private function resolveExistingTask(Message $message): ?Task
    {
        if ($message->taskId === null || $message->taskId === '') {
            return null;
        }

        $task = $this->requireTask($message->taskId);

        if ($message->contextId !== null && $message->contextId !== '' && $task->contextId !== null && $message->contextId !== $task->contextId) {
            throw InvalidParamsException::forField('contextId', 'contextId does not match the task.');
        }

        if ($task->status->state->isTerminal()) {
            throw new UnsupportedOperationException('Messages cannot be sent to a task in a terminal state.');
        }

        return $task;
    }

    private function createTask(Message $message): Task
    {
        $contextId = $message->contextId !== null && $message->contextId !== '' ? $message->contextId : Id::uuid();

        return new Task(
            id: Id::uuid(),
            status: TaskStatus::now(TaskState::Submitted),
            contextId: $contextId,
        );
    }

    private function requireTask(string $id): Task
    {
        $task = $this->store->find($id);

        if (! $task instanceof Task) {
            throw new TaskNotFoundException($id);
        }

        return $task;
    }

    private function applyEvents(Task $task, EventQueue $queue): Task
    {
        foreach ($queue->events() as $event) {
            if ($event instanceof Task) {
                $task = $event;
            }

            if ($event instanceof Message) {
                $task = $task->withMessage($event);
            }

            if ($event instanceof TaskStatusUpdateEvent) {
                $task = $task->withStatus($event->status);
            }

            if ($event instanceof TaskArtifactUpdateEvent) {
                $task = $task->withArtifact($event->artifact, $event->append === true);
            }
        }

        $this->store->save($task);
        $this->notify($task, $queue);

        return $task;
    }

    private function notify(Task $task, EventQueue $queue): void
    {
        foreach ($this->store->listPushConfigs($task->id) as $config) {
            foreach ($queue->events() as $event) {
                try {
                    $this->pushSender->send($config, StreamResponse::fromEvent($event));
                } catch (\Throwable) {
                    continue;
                }
            }
        }
    }

    private function maybeSavePushConfig(SendMessageRequest $request, Task $task): void
    {
        $config = $request->configuration?->taskPushNotificationConfig;

        if (! $config instanceof TaskPushNotificationConfig) {
            return;
        }

        $this->assertPush();
        $this->store->savePushConfig($config->withIds($config->id ?? Id::uuid(), $task->id));
    }

    private function withHistoryLength(Task $task, ?int $historyLength): Task
    {
        if ($historyLength === null) {
            return $task;
        }

        $history = $historyLength <= 0 ? [] : array_slice($task->history, -$historyLength);

        return new Task($task->id, $task->status, $task->contextId, $task->artifacts, $history, $task->metadata);
    }
}
