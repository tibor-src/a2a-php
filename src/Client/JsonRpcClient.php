<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Client;

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
use TiborSrc\A2A\Types\SendMessageRequest;
use TiborSrc\A2A\Types\SendMessageResponse;
use TiborSrc\A2A\Types\StreamResponse;
use TiborSrc\A2A\Types\SubscribeToTaskRequest;
use TiborSrc\A2A\Types\Task;
use TiborSrc\A2A\Types\TaskPushNotificationConfig;

final class JsonRpcClient extends HttpTransport implements Client
{
    public function sendMessage(SendMessageRequest $request): SendMessageResponse
    {
        return SendMessageResponse::fromArray($this->call('SendMessage', $request->toArray()));
    }

    public function sendStreamingMessage(SendMessageRequest $request): array
    {
        return $this->stream('SendStreamingMessage', $request->toArray());
    }

    public function getTask(GetTaskRequest $request): Task
    {
        return Task::fromArray($this->call('GetTask', $request->toArray()));
    }

    public function listTasks(ListTasksRequest $request): ListTasksResponse
    {
        return ListTasksResponse::fromArray($this->call('ListTasks', $request->toArray()));
    }

    public function cancelTask(CancelTaskRequest $request): Task
    {
        return Task::fromArray($this->call('CancelTask', $request->toArray()));
    }

    public function subscribeToTask(SubscribeToTaskRequest $request): array
    {
        return $this->stream('SubscribeToTask', $request->toArray());
    }

    public function createTaskPushNotificationConfig(TaskPushNotificationConfig $config): TaskPushNotificationConfig
    {
        return TaskPushNotificationConfig::fromArray($this->call('CreateTaskPushNotificationConfig', $config->toArray()));
    }

    public function getTaskPushNotificationConfig(GetTaskPushNotificationConfigRequest $request): TaskPushNotificationConfig
    {
        return TaskPushNotificationConfig::fromArray($this->call('GetTaskPushNotificationConfig', $request->toArray()));
    }

    public function listTaskPushNotificationConfigs(ListTaskPushNotificationConfigsRequest $request): ListTaskPushNotificationConfigsResponse
    {
        return ListTaskPushNotificationConfigsResponse::fromArray($this->call('ListTaskPushNotificationConfigs', $request->toArray()));
    }

    public function deleteTaskPushNotificationConfig(DeleteTaskPushNotificationConfigRequest $request): void
    {
        $this->call('DeleteTaskPushNotificationConfig', $request->toArray());
    }

    public function getExtendedAgentCard(GetExtendedAgentCardRequest $request): AgentCard
    {
        return AgentCard::fromArray($this->call('GetExtendedAgentCard', $request->toArray()));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(string $method, array $params): array
    {
        $id = bin2hex(random_bytes(8));
        $response = $this->send('POST', rtrim($this->baseUrl, '/') ?: $this->baseUrl, json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params,
        ], JSON_THROW_ON_ERROR), [
            'Content-Type' => Protocol::JSON_RPC_CONTENT_TYPE,
            'Accept' => Protocol::JSON_RPC_CONTENT_TYPE,
        ]);

        $payload = $this->decodeJson($response);

        if (isset($payload['error']) && is_array($payload['error'])) {
            /** @var array<string, mixed> $error */
            $error = $payload['error'];
            $this->throwError($error);
        }

        $result = $payload['result'] ?? [];

        if (! is_array($result)) {
            return [];
        }

        /** @var array<string, mixed> $result */
        return $result;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<StreamResponse>
     */
    private function stream(string $method, array $params): array
    {
        $id = bin2hex(random_bytes(8));
        $response = $this->send('POST', rtrim($this->baseUrl, '/') ?: $this->baseUrl, json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params,
        ], JSON_THROW_ON_ERROR), [
            'Content-Type' => Protocol::JSON_RPC_CONTENT_TYPE,
            'Accept' => Protocol::SSE_CONTENT_TYPE,
        ]);

        return $this->streamResponses($this->decodeSse($response), true);
    }
}
