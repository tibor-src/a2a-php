<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TiborSrc\A2A\Exceptions\A2AException;
use TiborSrc\A2A\Exceptions\InvalidRequestException;
use TiborSrc\A2A\Exceptions\MethodNotFoundException;
use TiborSrc\A2A\Exceptions\ParseException;
use TiborSrc\A2A\Protocol;
use TiborSrc\A2A\Types\CancelTaskRequest;
use TiborSrc\A2A\Types\DeleteTaskPushNotificationConfigRequest;
use TiborSrc\A2A\Types\GetExtendedAgentCardRequest;
use TiborSrc\A2A\Types\GetTaskPushNotificationConfigRequest;
use TiborSrc\A2A\Types\GetTaskRequest;
use TiborSrc\A2A\Types\ListTaskPushNotificationConfigsRequest;
use TiborSrc\A2A\Types\ListTasksRequest;
use TiborSrc\A2A\Types\SendMessageRequest;
use TiborSrc\A2A\Types\StreamResponse;
use TiborSrc\A2A\Types\SubscribeToTaskRequest;
use TiborSrc\A2A\Types\TaskPushNotificationConfig;

final readonly class Application implements RequestHandlerInterface
{
    public function __construct(
        private RequestHandler $handler,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $this->path($request);

        if ($request->getMethod() === 'GET' && ($path === Protocol::AGENT_CARD_PATH || $path === '/.well-known/agent-card.json')) {
            return $this->json($this->handler->agentCard()->toArray(), Protocol::HTTP_JSON_CONTENT_TYPE, extra: [
                'Cache-Control' => 'max-age=300',
                'ETag' => '"'.$this->handler->agentCard()->version.'"',
            ]);
        }

        try {
            $version = $request->getHeaderLine(Protocol::VERSION_HEADER);
            $this->handler->assertVersion($version === '' ? null : $version);
            $extensionsHeader = $request->getHeaderLine(Protocol::EXTENSIONS_HEADER);
            $this->handler->assertExtensions($extensionsHeader === '' ? [] : array_map(trim(...), explode(',', $extensionsHeader)));

            if ($this->isJsonRpc($request, $path)) {
                return $this->handleJsonRpc($request);
            }

            return $this->handleRest($request, $path);
        } catch (A2AException $a2AException) {
            if ($this->isJsonRpc($request, $path)) {
                $id = $this->jsonRpcId($request);

                return $this->json([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => $a2AException->jsonRpcCode,
                        'message' => $a2AException->getMessage(),
                        'data' => $a2AException->errorInfoDetails(),
                    ],
                ]);
            }

            return $this->json([
                'error' => [
                    'code' => $a2AException->httpStatus,
                    'status' => $a2AException->grpcStatus !== ''
                        ? $a2AException->grpcStatus
                        : $this->httpStatusName($a2AException->httpStatus),
                    'message' => $a2AException->getMessage(),
                    'details' => $a2AException->errorInfoDetails(),
                ],
            ], Protocol::HTTP_JSON_CONTENT_TYPE, $a2AException->httpStatus);
        }
    }

    private function handleJsonRpc(ServerRequestInterface $request): ResponseInterface
    {
        $payload = $this->decode($request);

        if (($payload['jsonrpc'] ?? null) !== '2.0') {
            throw new InvalidRequestException;
        }

        $method = $payload['method'] ?? null;
        $id = $payload['id'] ?? null;

        if (! is_string($method)) {
            throw new InvalidRequestException;
        }

        $params = $payload['params'] ?? [];

        if (! is_array($params)) {
            $params = [];
        }

        /** @var array<string, mixed> $params */
        return match ($method) {
            'SendMessage' => $this->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $this->handler->sendMessage(SendMessageRequest::fromArray($params))->toArray(),
            ]),
            'SendStreamingMessage' => $this->sse(
                array_map(
                    fn (StreamResponse $event): array => [
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'result' => $event->toArray(),
                    ],
                    $this->handler->sendStreamingMessage(SendMessageRequest::fromArray($params)),
                ),
            ),
            'GetTask' => $this->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $this->handler->getTask(GetTaskRequest::fromArray($params))->toArray(),
            ]),
            'ListTasks' => $this->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $this->handler->listTasks(ListTasksRequest::fromArray($params))->toArray(),
            ]),
            'CancelTask' => $this->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $this->handler->cancelTask(CancelTaskRequest::fromArray($params))->toArray(),
            ]),
            'SubscribeToTask' => $this->sse(
                array_map(
                    fn (StreamResponse $event): array => [
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'result' => $event->toArray(),
                    ],
                    $this->handler->subscribeToTask(SubscribeToTaskRequest::fromArray($params)),
                ),
            ),
            'CreateTaskPushNotificationConfig' => $this->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $this->handler->createPushConfig(TaskPushNotificationConfig::fromArray($params))->toArray(),
            ]),
            'GetTaskPushNotificationConfig' => $this->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $this->handler->getPushConfig(GetTaskPushNotificationConfigRequest::fromArray($params))->toArray(),
            ]),
            'ListTaskPushNotificationConfigs' => $this->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $this->handler->listPushConfigs(ListTaskPushNotificationConfigsRequest::fromArray($params))->toArray(),
            ]),
            'DeleteTaskPushNotificationConfig' => $this->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => new \stdClass,
            ], extra: $this->deleteJsonRpc($params, $id)),
            'GetExtendedAgentCard' => $this->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $this->handler->getExtendedAgentCard(GetExtendedAgentCardRequest::fromArray($params))->toArray(),
            ]),
            default => throw new MethodNotFoundException($method),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, string>
     */
    private function deleteJsonRpc(array $params, mixed $id): array
    {
        $this->handler->deletePushConfig(DeleteTaskPushNotificationConfigRequest::fromArray($params));
        unset($id);

        return [];
    }

    private function handleRest(ServerRequestInterface $request, string $path): ResponseInterface
    {
        $method = $request->getMethod();
        $query = [];

        foreach ($request->getQueryParams() as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (is_array($value)) {
                $parts = [];

                foreach ($value as $item) {
                    if (is_scalar($item)) {
                        $parts[] = (string) $item;
                    }
                }

                $query[$key] = implode(',', $parts);

                continue;
            }

            if (is_scalar($value)) {
                $query[$key] = (string) $value;
            }
        }

        if ($method === 'POST' && $path === '/message:send') {
            return $this->json(
                $this->handler->sendMessage(SendMessageRequest::fromArray($this->decode($request)))->toArray(),
                Protocol::HTTP_JSON_CONTENT_TYPE,
            );
        }

        if ($method === 'POST' && $path === '/message:stream') {
            return $this->sse(
                array_map(
                    fn (StreamResponse $event): array => $event->toArray(),
                    $this->handler->sendStreamingMessage(SendMessageRequest::fromArray($this->decode($request))),
                ),
            );
        }

        if ($method === 'GET' && $path === '/tasks') {
            return $this->json(
                $this->handler->listTasks(ListTasksRequest::fromArray($query))->toArray(),
                Protocol::HTTP_JSON_CONTENT_TYPE,
            );
        }

        if ($method === 'GET' && $path === '/extendedAgentCard') {
            return $this->json(
                $this->handler->getExtendedAgentCard(GetExtendedAgentCardRequest::fromArray($query))->toArray(),
                Protocol::HTTP_JSON_CONTENT_TYPE,
            );
        }

        if (preg_match('#^/tasks/([^/]+):cancel$#', $path, $matches) === 1 && $method === 'POST') {
            $body = $this->decode($request);
            $body['id'] = urldecode($matches[1]);

            return $this->json(
                $this->handler->cancelTask(CancelTaskRequest::fromArray($body))->toArray(),
                Protocol::HTTP_JSON_CONTENT_TYPE,
            );
        }

        if (preg_match('#^/tasks/([^/]+):subscribe$#', $path, $matches) === 1 && in_array($method, ['GET', 'POST'], true)) {
            return $this->sse(
                array_map(
                    fn (StreamResponse $event): array => $event->toArray(),
                    $this->handler->subscribeToTask(new SubscribeToTaskRequest(urldecode($matches[1]))),
                ),
            );
        }

        if (preg_match('#^/tasks/([^/]+)/pushNotificationConfigs/([^/]+)$#', $path, $matches) === 1) {
            $taskId = urldecode($matches[1]);
            $configId = urldecode($matches[2]);

            if ($method === 'GET') {
                return $this->json(
                    $this->handler->getPushConfig(new GetTaskPushNotificationConfigRequest($taskId, $configId))->toArray(),
                    Protocol::HTTP_JSON_CONTENT_TYPE,
                );
            }

            if ($method === 'DELETE') {
                $this->handler->deletePushConfig(new DeleteTaskPushNotificationConfigRequest($taskId, $configId));

                return $this->responses->createResponse(200)->withBody($this->streams->createStream('{}'));
            }
        }

        if (preg_match('#^/tasks/([^/]+)/pushNotificationConfigs$#', $path, $matches) === 1) {
            $taskId = urldecode($matches[1]);

            if ($method === 'POST') {
                $body = $this->decode($request);
                $body['taskId'] = $taskId;

                return $this->json(
                    $this->handler->createPushConfig(TaskPushNotificationConfig::fromArray($body))->toArray(),
                    Protocol::HTTP_JSON_CONTENT_TYPE,
                );
            }

            if ($method === 'GET') {
                return $this->json(
                    $this->handler->listPushConfigs(new ListTaskPushNotificationConfigsRequest($taskId))->toArray(),
                    Protocol::HTTP_JSON_CONTENT_TYPE,
                );
            }
        }

        if (preg_match('#^/tasks/([^/]+)$#', $path, $matches) === 1 && $method === 'GET') {
            $query['id'] = urldecode($matches[1]);

            return $this->json(
                $this->handler->getTask(GetTaskRequest::fromArray($query))->toArray(),
                Protocol::HTTP_JSON_CONTENT_TYPE,
            );
        }

        throw new MethodNotFoundException($method.' '.$path);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ServerRequestInterface $request): array
    {
        $body = (string) $request->getBody();

        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ParseException;
        }

        if (! is_array($decoded)) {
            throw new InvalidRequestException;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function isJsonRpc(ServerRequestInterface $request, string $path): bool
    {
        if ($path === '/rpc') {
            return true;
        }

        $contentType = $request->getHeaderLine('Content-Type');

        if ($request->getMethod() === 'POST' && ($path === '/' || $path === '') && str_contains($contentType, 'application/json') && ! str_contains($contentType, 'a2a+json')) {
            return true;
        }

        $body = (string) $request->getBody();

        if ($body !== '' && str_contains($body, '"jsonrpc"')) {
            $request->getBody()->rewind();

            return true;
        }

        $request->getBody()->rewind();

        return false;
    }

    private function jsonRpcId(ServerRequestInterface $request): mixed
    {
        try {
            $payload = $this->decode($request);

            return $payload['id'] ?? null;
        } catch (A2AException) {
            return null;
        }
    }

    private function path(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();

        return $path === '' ? '/' : $path;
    }

    /**
     * @param  array<string, mixed>|\stdClass  $payload
     * @param  array<string, string>  $extra
     */
    private function json(array|\stdClass $payload, string $contentType = Protocol::JSON_RPC_CONTENT_TYPE, int $status = 200, array $extra = []): ResponseInterface
    {
        $response = $this->responses
            ->createResponse($status)
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streams->createStream(json_encode($payload, JSON_THROW_ON_ERROR)));

        foreach ($extra as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function sse(array $events): ResponseInterface
    {
        $body = '';

        foreach ($events as $event) {
            $body .= 'data: '.json_encode($event, JSON_THROW_ON_ERROR)."\n\n";
        }

        return $this->responses
            ->createResponse(200)
            ->withHeader('Content-Type', Protocol::SSE_CONTENT_TYPE)
            ->withHeader('Cache-Control', 'no-cache')
            ->withBody($this->streams->createStream($body));
    }

    private function httpStatusName(int $status): string
    {
        return match ($status) {
            400 => 'INVALID_ARGUMENT',
            404 => 'NOT_FOUND',
            500 => 'INTERNAL',
            default => 'UNKNOWN',
        };
    }
}
