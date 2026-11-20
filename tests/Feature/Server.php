<?php

declare(strict_types=1);

use Nyholm\Psr7\ServerRequest;
use TiborSrc\A2A\Protocol;
use TiborSrc\A2A\Server\CompletingExecutor;
use TiborSrc\A2A\Server\InMemoryTaskStore;
use TiborSrc\A2A\Server\RequestHandler;

it('serves the agent card', function (): void {
    $response = application()->handle(new ServerRequest('GET', 'http://127.0.0.1:9999/.well-known/agent-card.json'));
    $card = decode($response);

    expect($response->getStatusCode())->toBe(200)
        ->and(jsonString($card, 'name'))->toBe('Test Agent')
        ->and(jsonString($card, 'supportedInterfaces', 0, 'protocolBinding'))->toBe(Protocol::JSON_RPC_BINDING);
});

it('sends a message over json-rpc and rest', function (): void {
    $app = application();
    $rpc = decode(jsonRpc($app, 'SendMessage', [
        'message' => [
            'messageId' => 'tck-complete-task-1',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'hello']],
        ],
    ]));

    expect(jsonString($rpc, 'result', 'task', 'status', 'state'))->toBe('TASK_STATE_COMPLETED');

    $rest = decode(rest($app, 'POST', '/message:send', [
        'message' => [
            'messageId' => 'tck-complete-task-2',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'hello']],
        ],
    ]));

    expect(jsonString($rest, 'task', 'status', 'state'))->toBe('TASK_STATE_COMPLETED');
});

it('returns input required and then completes on follow-up', function (): void {
    $app = application();
    $created = decode(jsonRpc($app, 'SendMessage', [
        'message' => [
            'messageId' => 'tck-input-required-1',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'wait']],
        ],
    ]));
    $taskId = jsonString($created, 'result', 'task', 'id');

    expect(jsonString($created, 'result', 'task', 'status', 'state'))->toBe('TASK_STATE_INPUT_REQUIRED');

    $got = decode(jsonRpc($app, 'GetTask', ['id' => $taskId]));
    expect(jsonString($got, 'result', 'id'))->toBe($taskId);

    $completed = decode(jsonRpc($app, 'SendMessage', [
        'message' => [
            'messageId' => 'tck-complete-task-follow',
            'role' => 'ROLE_USER',
            'taskId' => $taskId,
            'parts' => [['text' => 'done']],
        ],
    ]));

    expect(jsonString($completed, 'result', 'task', 'status', 'state'))->toBe('TASK_STATE_COMPLETED');
});

it('cancels a working task and rejects cancel of a terminal task', function (): void {
    $app = application();
    $created = decode(jsonRpc($app, 'SendMessage', [
        'message' => [
            'messageId' => 'tck-input-required-cancel',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'wait']],
        ],
    ]));
    $taskId = jsonString($created, 'result', 'task', 'id');

    $canceled = decode(rest($app, 'POST', '/tasks/'.$taskId.':cancel', ['id' => $taskId]));
    expect(jsonString($canceled, 'status', 'state'))->toBe('TASK_STATE_CANCELED');

    $again = decode(jsonRpc($app, 'CancelTask', ['id' => $taskId]));
    expect(jsonAt($again, 'error', 'code'))->toBe(-32002);
});

it('lists tasks and manages push configs', function (): void {
    $app = application();
    $created = decode(jsonRpc($app, 'SendMessage', [
        'message' => [
            'messageId' => 'tck-complete-task-list',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'hello']],
        ],
    ]));
    $taskId = jsonString($created, 'result', 'task', 'id');

    $listed = decode(rest($app, 'GET', '/tasks', query: ['pageSize' => 10]));
    expect(jsonAt($listed, 'totalSize'))->toBeGreaterThan(0);

    $createdConfig = decode(rest($app, 'POST', '/tasks/'.$taskId.'/pushNotificationConfigs', [
        'url' => 'https://example.com/hook',
        'token' => 'secret',
        'authentication' => ['scheme' => 'Bearer', 'credentials' => 'token'],
    ]));
    $configId = jsonString($createdConfig, 'id');

    $got = decode(rest($app, 'GET', '/tasks/'.$taskId.'/pushNotificationConfigs/'.$configId));
    expect(jsonString($got, 'url'))->toBe('https://example.com/hook');

    $all = decode(jsonRpc($app, 'ListTaskPushNotificationConfigs', ['taskId' => $taskId]));
    expect(jsonAt($all, 'result', 'configs'))->not->toBeEmpty();

    $deleted = rest($app, 'DELETE', '/tasks/'.$taskId.'/pushNotificationConfigs/'.$configId);
    expect($deleted->getStatusCode())->toBe(200);
});

it('streams messages and subscribes', function (): void {
    $app = application();
    $stream = jsonRpc($app, 'SendStreamingMessage', [
        'message' => [
            'messageId' => 'tck-stream-001-x',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'stream']],
        ],
    ]);

    expect($stream->getHeaderLine('Content-Type'))->toBe(Protocol::SSE_CONTENT_TYPE)
        ->and((string) $stream->getBody())->toContain('TASK_STATE_COMPLETED');

    $working = decode(jsonRpc($app, 'SendMessage', [
        'message' => [
            'messageId' => 'tck-input-required-sub',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'wait']],
        ],
    ]));
    $taskId = jsonString($working, 'result', 'task', 'id');

    $sub = rest($app, 'POST', '/tasks/'.$taskId.':subscribe');
    expect($sub->getHeaderLine('Content-Type'))->toBe(Protocol::SSE_CONTENT_TYPE);
});

it('returns protocol errors', function (): void {
    $app = application();

    expect(jsonAt(decode(jsonRpc($app, 'Missing')), 'error', 'code'))->toBe(-32601)
        ->and(jsonAt(decode(jsonRpc($app, 'GetTask', ['id' => 'missing'])), 'error', 'code'))->toBe(-32001)
        ->and(jsonAt(decode(application()->handle(new ServerRequest('POST', 'http://127.0.0.1:9999/', [
            'Content-Type' => 'application/json',
            Protocol::VERSION_HEADER => '9.9',
        ], '{"jsonrpc":"2.0","id":1,"method":"GetTask","params":{"id":"x"}}'))), 'error', 'code'))->toBe(-32009);

    $completed = decode(jsonRpc($app, 'SendMessage', [
        'message' => [
            'messageId' => 'tck-complete-task-term',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'hello']],
        ],
    ]));
    $taskId = jsonString($completed, 'result', 'task', 'id');
    $follow = decode(jsonRpc($app, 'SendMessage', [
        'message' => [
            'messageId' => 'tck-terminal-1',
            'role' => 'ROLE_USER',
            'taskId' => $taskId,
            'parts' => [['text' => 'again']],
        ],
    ]));
    expect(jsonAt($follow, 'error', 'code'))->toBe(-32004);
});

it('maps http+json aip-193 statuses and accepts json-rpc snake_case params', function (): void {
    $app = application();

    $version = decode($app->handle(new ServerRequest('POST', 'http://127.0.0.1:9999/message:send', [
        'Content-Type' => Protocol::HTTP_JSON_CONTENT_TYPE,
        Protocol::VERSION_HEADER => '99.0',
    ], json_encode([
        'message' => [
            'messageId' => 'tck-ver-002-rest',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'version test']],
        ],
    ], JSON_THROW_ON_ERROR))));

    expect(jsonAt($version, 'error', 'status'))->toBe('FAILED_PRECONDITION')
        ->and(jsonAt($version, 'error', 'code'))->toBe(400);

    $created = decode(jsonRpc($app, 'SendMessage', [
        'message' => [
            'messageId' => 'tck-complete-task-hist',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'hello']],
        ],
    ]));
    $taskId = jsonString($created, 'result', 'task', 'id');

    $history = jsonAt(decode(jsonRpc($app, 'GetTask', [
        'id' => $taskId,
        'history_length' => 1,
    ])), 'result', 'history');

    expect(is_array($history) ? count($history) : 0)->toBeLessThanOrEqual(1);

    $push = decode(jsonRpc($app, 'CreateTaskPushNotificationConfig', [
        'task_id' => $taskId,
        'url' => 'https://example.com/hook',
        'id' => 'push-snake',
    ]));

    expect(jsonString($push, 'result', 'taskId'))->toBe($taskId)
        ->and(jsonString($push, 'result', 'url'))->toBe('https://example.com/hook');

    $cancel = decode(rest($app, 'POST', '/tasks/'.$taskId.':cancel', ['id' => $taskId]));
    expect(jsonAt($cancel, 'error', 'status'))->toBe('FAILED_PRECONDITION');

    $subscribe = decode(rest($app, 'POST', '/tasks/'.$taskId.':subscribe'));
    expect(jsonAt($subscribe, 'error', 'status'))->toBe('FAILED_PRECONDITION')
        ->and(jsonAt($subscribe, 'error', 'code'))->toBe(400);
});

it('returns a direct message and artifacts', function (): void {
    $app = application();
    $message = decode(jsonRpc($app, 'SendMessage', [
        'message' => [
            'messageId' => 'tck-message-response-1',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'hi']],
        ],
    ]));
    expect(jsonString($message, 'result', 'message', 'parts', 0, 'text'))->toBe('Direct message response');

    $artifact = decode(jsonRpc($app, 'SendMessage', [
        'message' => [
            'messageId' => 'tck-artifact-text-1',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'hi']],
        ],
    ]));
    expect(jsonString($artifact, 'result', 'task', 'artifacts', 0, 'parts', 0, 'text'))->toBe('Generated text content');
});

it('uses the completing executor', function (): void {
    $handler = new RequestHandler(agentCard(), new CompletingExecutor, new InMemoryTaskStore);
    $app = application($handler);
    $result = decode(jsonRpc($app, 'SendMessage', [
        'message' => [
            'messageId' => 'plain-1',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'echo me']],
        ],
    ]));

    expect(jsonString($result, 'result', 'task', 'status', 'message', 'parts', 0, 'text'))->toBe('echo me');
});

it('fetches the extended agent card', function (): void {
    $card = decode(jsonRpc(application(), 'GetExtendedAgentCard'));
    expect(jsonString($card, 'result', 'name'))->toBe('Test Agent');
});
