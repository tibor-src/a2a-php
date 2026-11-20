<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Client;

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
use TiborSrc\A2A\Types\SendMessageRequest;
use TiborSrc\A2A\Types\SendMessageResponse;
use TiborSrc\A2A\Types\StreamResponse;
use TiborSrc\A2A\Types\SubscribeToTaskRequest;
use TiborSrc\A2A\Types\Task;
use TiborSrc\A2A\Types\TaskPushNotificationConfig;

interface Client
{
    public function sendMessage(SendMessageRequest $request): SendMessageResponse;

    /**
     * @return list<StreamResponse>
     */
    public function sendStreamingMessage(SendMessageRequest $request): array;

    public function getTask(GetTaskRequest $request): Task;

    public function listTasks(ListTasksRequest $request): ListTasksResponse;

    public function cancelTask(CancelTaskRequest $request): Task;

    /**
     * @return list<StreamResponse>
     */
    public function subscribeToTask(SubscribeToTaskRequest $request): array;

    public function createTaskPushNotificationConfig(TaskPushNotificationConfig $config): TaskPushNotificationConfig;

    public function getTaskPushNotificationConfig(GetTaskPushNotificationConfigRequest $request): TaskPushNotificationConfig;

    public function listTaskPushNotificationConfigs(ListTaskPushNotificationConfigsRequest $request): ListTaskPushNotificationConfigsResponse;

    public function deleteTaskPushNotificationConfig(DeleteTaskPushNotificationConfigRequest $request): void;

    public function getExtendedAgentCard(GetExtendedAgentCardRequest $request): AgentCard;
}
