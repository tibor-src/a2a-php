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

final class HttpJsonClient extends HttpTransport implements Client
{
    public function sendMessage(SendMessageRequest $request): SendMessageResponse
    {
        return SendMessageResponse::fromArray($this->call('POST', '/message:send', $request->toArray()));
    }

    public function sendStreamingMessage(SendMessageRequest $request): array
    {
        return $this->stream('POST', '/message:stream', $request->toArray());
    }

    public function getTask(GetTaskRequest $request): Task
    {
        $query = $request->historyLength === null ? '' : '?historyLength='.$request->historyLength;

        return Task::fromArray($this->call('GET', '/tasks/'.rawurlencode($request->id).$query, null));
    }

    public function listTasks(ListTasksRequest $request): ListTasksResponse
    {
        $query = http_build_query($request->toArray());

        return ListTasksResponse::fromArray($this->call('GET', '/tasks'.($query === '' ? '' : '?'.$query), null));
    }

    public function cancelTask(CancelTaskRequest $request): Task
    {
        return Task::fromArray($this->call('POST', '/tasks/'.rawurlencode($request->id).':cancel', $request->toArray()));
    }

    public function subscribeToTask(SubscribeToTaskRequest $request): array
    {
        return $this->stream('POST', '/tasks/'.rawurlencode($request->id).':subscribe', $request->toArray());
    }

    public function createTaskPushNotificationConfig(TaskPushNotificationConfig $config): TaskPushNotificationConfig
    {
        $taskId = $config->taskId ?? '';

        return TaskPushNotificationConfig::fromArray(
            $this->call('POST', '/tasks/'.rawurlencode($taskId).'/pushNotificationConfigs', $config->toArray()),
        );
    }

    public function getTaskPushNotificationConfig(GetTaskPushNotificationConfigRequest $request): TaskPushNotificationConfig
    {
        return TaskPushNotificationConfig::fromArray($this->call(
            'GET',
            '/tasks/'.rawurlencode($request->taskId).'/pushNotificationConfigs/'.rawurlencode($request->id),
            null,
        ));
    }

    public function listTaskPushNotificationConfigs(ListTaskPushNotificationConfigsRequest $request): ListTaskPushNotificationConfigsResponse
    {
        return ListTaskPushNotificationConfigsResponse::fromArray($this->call(
            'GET',
            '/tasks/'.rawurlencode($request->taskId).'/pushNotificationConfigs',
            null,
        ));
    }

    public function deleteTaskPushNotificationConfig(DeleteTaskPushNotificationConfigRequest $request): void
    {
        $this->call(
            'DELETE',
            '/tasks/'.rawurlencode($request->taskId).'/pushNotificationConfigs/'.rawurlencode($request->id),
            null,
        );
    }

    public function getExtendedAgentCard(GetExtendedAgentCardRequest $request): AgentCard
    {
        unset($request);

        return AgentCard::fromArray($this->call('GET', '/extendedAgentCard', null));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, ?array $payload): array
    {
        $body = $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR);
        $response = $this->send($method, $this->url($path), $body, [
            'Content-Type' => Protocol::HTTP_JSON_CONTENT_TYPE,
            'Accept' => Protocol::HTTP_JSON_CONTENT_TYPE,
        ]);
        $decoded = $this->decodeJson($response);

        if ($response->getStatusCode() >= 400) {
            $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : $decoded;
            /** @var array<string, mixed> $error */
            $this->throwError($error, $response->getStatusCode());
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return list<StreamResponse>
     */
    private function stream(string $method, string $path, ?array $payload): array
    {
        $body = $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR);
        $response = $this->send($method, $this->url($path), $body, [
            'Content-Type' => Protocol::HTTP_JSON_CONTENT_TYPE,
            'Accept' => Protocol::SSE_CONTENT_TYPE,
        ]);

        return $this->streamResponses($this->decodeSse($response), false);
    }
}
