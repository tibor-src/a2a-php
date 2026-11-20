<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use TiborSrc\A2A\Card\AgentCardSigner;
use TiborSrc\A2A\Card\AgentCardVerifier;
use TiborSrc\A2A\Client\AgentCardResolver;
use TiborSrc\A2A\Client\Auth;
use TiborSrc\A2A\Client\ClientFactory;
use TiborSrc\A2A\Client\HttpJsonClient;
use TiborSrc\A2A\Client\JsonRpcClient;
use TiborSrc\A2A\Exceptions\TaskNotFoundException;
use TiborSrc\A2A\Id;
use TiborSrc\A2A\Protocol;
use TiborSrc\A2A\Server\FileTaskStore;
use TiborSrc\A2A\Server\HttpPushNotificationSender;
use TiborSrc\A2A\Server\InMemoryTaskStore;
use TiborSrc\A2A\Server\NullPushNotificationSender;
use TiborSrc\A2A\Types\AgentCard;
use TiborSrc\A2A\Types\AuthenticationInfo;
use TiborSrc\A2A\Types\CancelTaskRequest;
use TiborSrc\A2A\Types\DeleteTaskPushNotificationConfigRequest;
use TiborSrc\A2A\Types\GetExtendedAgentCardRequest;
use TiborSrc\A2A\Types\GetTaskPushNotificationConfigRequest;
use TiborSrc\A2A\Types\GetTaskRequest;
use TiborSrc\A2A\Types\ListTaskPushNotificationConfigsRequest;
use TiborSrc\A2A\Types\ListTasksRequest;
use TiborSrc\A2A\Types\Message;
use TiborSrc\A2A\Types\Part;
use TiborSrc\A2A\Types\Role;
use TiborSrc\A2A\Types\SendMessageRequest;
use TiborSrc\A2A\Types\StreamResponse;
use TiborSrc\A2A\Types\SubscribeToTaskRequest;
use TiborSrc\A2A\Types\Task;
use TiborSrc\A2A\Types\TaskPushNotificationConfig;
use TiborSrc\A2A\Types\TaskState;
use TiborSrc\A2A\Types\TaskStatus;

it('discovers an agent card and talks json-rpc', function (): void {
    $card = agentCard()->toArray();
    $http = new FakeHttp([
        new Response(200, [], json_encode($card, JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode([
            'jsonrpc' => '2.0',
            'id' => '1',
            'result' => ['task' => new Task('t1', TaskStatus::now(TaskState::Completed), 'c1')->toArray()],
        ], JSON_THROW_ON_ERROR)),
    ]);
    $client = ClientFactory::create($http)->connect('http://127.0.0.1:9999', Auth::bearer('token'));

    $result = $client->sendMessage(new SendMessageRequest(new Message('m1', Role::User, [Part::text('hi')])));

    expect($result->task?->id)->toBe('t1')
        ->and($http->requests[1]->getHeaderLine('Authorization'))->toBe('Bearer token');
});

it('uses the http+json client', function (): void {
    $http = new FakeHttp([
        new Response(200, [], json_encode(['task' => new Task('t1', TaskStatus::now(TaskState::Completed), 'c1')->toArray()], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode(['id' => 't1', 'status' => TaskStatus::now(TaskState::Completed)->toArray()], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode(['tasks' => [], 'nextPageToken' => '', 'pageSize' => 50, 'totalSize' => 0], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode(['id' => 't1', 'status' => TaskStatus::now(TaskState::Canceled)->toArray()], JSON_THROW_ON_ERROR)),
        new Response(200, ['Content-Type' => Protocol::SSE_CONTENT_TYPE], "data: {\"task\":{\"id\":\"t1\",\"status\":{\"state\":\"TASK_STATE_COMPLETED\"}}}\n\n"),
        new Response(200, [], json_encode(['url' => 'https://example.com', 'id' => 'p1', 'taskId' => 't1'], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode(['url' => 'https://example.com', 'id' => 'p1', 'taskId' => 't1'], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode(['configs' => [], 'nextPageToken' => ''], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode(agentCard()->toArray(), JSON_THROW_ON_ERROR)),
        new Response(200, [], '{}'),
        new Response(200, ['Content-Type' => Protocol::SSE_CONTENT_TYPE], "data: {\"statusUpdate\":{\"taskId\":\"t1\",\"contextId\":\"c1\",\"status\":{\"state\":\"TASK_STATE_WORKING\"}}}\n\n"),
    ]);
    $client = new HttpJsonClient($http, Psr17FactoryDiscovery::findRequestFactory(), Psr17FactoryDiscovery::findStreamFactory(), 'http://127.0.0.1:9999', Auth::none());

    expect($client->sendMessage(new SendMessageRequest(new Message('m1', Role::User, [Part::text('hi')])))->task?->id)->toBe('t1')
        ->and($client->getTask(new GetTaskRequest('t1', historyLength: 1))->id)->toBe('t1')
        ->and($client->listTasks(new ListTasksRequest)->totalSize)->toBe(0)
        ->and($client->cancelTask(new CancelTaskRequest('t1'))->status->state)->toBe(TaskState::Canceled)
        ->and($client->sendStreamingMessage(new SendMessageRequest(new Message('m1', Role::User, [Part::text('hi')]))))->not->toBeEmpty()
        ->and($client->createTaskPushNotificationConfig(new TaskPushNotificationConfig('https://example.com', taskId: 't1'))->id)->toBe('p1')
        ->and($client->getTaskPushNotificationConfig(new GetTaskPushNotificationConfigRequest('t1', 'p1'))->id)->toBe('p1')
        ->and($client->listTaskPushNotificationConfigs(new ListTaskPushNotificationConfigsRequest('t1'))->configs)->toBeEmpty()
        ->and($client->getExtendedAgentCard(new GetExtendedAgentCardRequest)->name)->toBe('Test Agent');

    $client->deleteTaskPushNotificationConfig(new DeleteTaskPushNotificationConfigRequest('t1', 'p1'));
    $client->subscribeToTask(new SubscribeToTaskRequest('t1'));
});

it('maps json-rpc errors', function (): void {
    $http = new FakeHttp([
        new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['code' => -32001, 'message' => 'missing']], JSON_THROW_ON_ERROR)),
    ]);
    $client = new JsonRpcClient($http, Psr17FactoryDiscovery::findRequestFactory(), Psr17FactoryDiscovery::findStreamFactory(), 'http://127.0.0.1:9999');

    $client->getTask(new GetTaskRequest('missing'));
})->throws(TaskNotFoundException::class);

it('resolves cards, stores files, signs cards, and sends push notifications', function (): void {
    $http = new FakeHttp([
        new Response(200, [], json_encode(agentCard()->toArray(), JSON_THROW_ON_ERROR)),
        new Response(200, [], '{}'),
    ]);
    $resolver = new AgentCardResolver($http, Psr17FactoryDiscovery::findRequestFactory());
    expect($resolver->resolve('http://127.0.0.1:9999')->name)->toBe('Test Agent');

    $path = sys_get_temp_dir().'/a2a-php-store-'.Id::uuid().'.json';
    $store = new FileTaskStore($path);
    $task = new Task('t1', TaskStatus::now(TaskState::Working), 'c1');
    $store->save($task);
    $store->savePushConfig(new TaskPushNotificationConfig('https://example.com', id: 'p1', taskId: 't1'));
    expect($store->find('t1')?->id)->toBe('t1')
        ->and($store->listPushConfigs('t1'))->toHaveCount(1)
        ->and($store->all())->not->toBeEmpty();
    $store->deletePushConfig('t1', 'p1');
    unlink($path);

    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);

    if ($key === false) {
        throw new RuntimeException('Unable to generate a signing key.');
    }

    $details = openssl_pkey_get_details($key);
    $pem = is_array($details) ? $details['key'] ?? null : null;

    if (! is_string($pem)) {
        throw new RuntimeException('Unable to export the public key.');
    }

    $public = openssl_pkey_get_public($pem);

    if ($public === false) {
        throw new RuntimeException('Unable to load the public key.');
    }

    $card = agentCard();
    $signature = (new AgentCardSigner)->sign($card, $key, ['alg' => 'RS256', 'typ' => 'JOSE', 'kid' => 'k1']);
    $signed = new AgentCard(
        $card->name,
        $card->description,
        $card->supportedInterfaces,
        $card->version,
        $card->capabilities,
        $card->defaultInputModes,
        $card->defaultOutputModes,
        $card->skills,
        signatures: [$signature],
    );
    expect((new AgentCardVerifier)->verify($signed, $public))->toBeTrue();

    $sender = new HttpPushNotificationSender($http, Psr17FactoryDiscovery::findRequestFactory(), Psr17FactoryDiscovery::findStreamFactory());
    $sender->send(new TaskPushNotificationConfig('https://example.com', token: 't', authentication: new AuthenticationInfo('Bearer', 'abc')), StreamResponse::fromEvent($task));
    (new NullPushNotificationSender)->send(new TaskPushNotificationConfig('https://example.com'), StreamResponse::fromEvent($task));
    expect((new InMemoryTaskStore)->find('nope'))->toBeNull()
        ->and(Id::uuid())->toMatch('/^[0-9a-f-]{36}$/');
});
