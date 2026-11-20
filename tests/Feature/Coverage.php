<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Http\Discovery\Psr17FactoryDiscovery;
use Nyholm\Psr7\ServerRequest;
use TiborSrc\A2A\Card\AgentCardSigner;
use TiborSrc\A2A\Card\AgentCardVerifier;
use TiborSrc\A2A\Client\AgentCardResolver;
use TiborSrc\A2A\Client\Auth;
use TiborSrc\A2A\Client\ClientFactory;
use TiborSrc\A2A\Client\HttpJsonClient;
use TiborSrc\A2A\Client\JsonRpcClient;
use TiborSrc\A2A\Exceptions\ContentTypeNotSupportedException;
use TiborSrc\A2A\Exceptions\ExtendedAgentCardNotConfiguredException;
use TiborSrc\A2A\Exceptions\ExtensionSupportRequiredException;
use TiborSrc\A2A\Exceptions\InternalErrorException;
use TiborSrc\A2A\Exceptions\InvalidAgentResponseException;
use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Exceptions\InvalidRequestException;
use TiborSrc\A2A\Exceptions\ParseException;
use TiborSrc\A2A\Exceptions\PushNotificationNotSupportedException;
use TiborSrc\A2A\Exceptions\TaskNotFoundException;
use TiborSrc\A2A\Exceptions\UnsupportedOperationException;
use TiborSrc\A2A\Exceptions\VersionNotSupportedException;
use TiborSrc\A2A\Id;
use TiborSrc\A2A\Json\CanonicalJson;
use TiborSrc\A2A\Json\Payload;
use TiborSrc\A2A\Protocol;
use TiborSrc\A2A\Server\AgentExecutor;
use TiborSrc\A2A\Server\Application;
use TiborSrc\A2A\Server\CompletingExecutor;
use TiborSrc\A2A\Server\EventQueue;
use TiborSrc\A2A\Server\FileTaskStore;
use TiborSrc\A2A\Server\HttpPushNotificationSender;
use TiborSrc\A2A\Server\InMemoryTaskStore;
use TiborSrc\A2A\Server\PushNotificationSender;
use TiborSrc\A2A\Server\RequestContext;
use TiborSrc\A2A\Server\RequestHandler;
use TiborSrc\A2A\Server\TaskStore;
use TiborSrc\A2A\Server\TckExecutor;
use TiborSrc\A2A\Types\AgentCapabilities;
use TiborSrc\A2A\Types\AgentCard;
use TiborSrc\A2A\Types\AgentCardSignature;
use TiborSrc\A2A\Types\AgentExtension;
use TiborSrc\A2A\Types\AgentInterface;
use TiborSrc\A2A\Types\AgentSkill;
use TiborSrc\A2A\Types\Artifact;
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
use TiborSrc\A2A\Types\SecurityRequirement;
use TiborSrc\A2A\Types\SecurityScheme;
use TiborSrc\A2A\Types\SendMessageConfiguration;
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

final class SilentExecutor implements AgentExecutor
{
    public function execute(RequestContext $context, EventQueue $queue): void
    {
        if ($context->task instanceof Task) {
            $queue->write($context->task);
        }
    }

    public function cancel(RequestContext $context, EventQueue $queue): void {}
}

final class WorkingExecutor implements AgentExecutor
{
    public function execute(RequestContext $context, EventQueue $queue): void
    {
        $task = $context->task;

        if ($task instanceof Task) {
            $queue->write(new TaskStatusUpdateEvent($task->id, $task->contextId ?? $task->id, TaskStatus::now(TaskState::Working)));
        }
    }

    public function cancel(RequestContext $context, EventQueue $queue): void {}
}

final class EmptyExecutor implements AgentExecutor
{
    public function execute(RequestContext $context, EventQueue $queue): void {}

    public function cancel(RequestContext $context, EventQueue $queue): void {}
}

final class ThrowingPushSender implements PushNotificationSender
{
    public function send(TaskPushNotificationConfig $config, StreamResponse $payload): void
    {
        throw new RuntimeException('push failed');
    }
}

final class FlippingStore implements TaskStore
{
    private readonly InMemoryTaskStore $inner;

    private int $reads = 0;

    public function __construct()
    {
        $this->inner = new InMemoryTaskStore;
    }

    public function save(Task $task): void
    {
        $this->inner->save($task);
    }

    public function find(string $id): ?Task
    {
        $task = $this->inner->find($id);
        $this->reads++;

        if ($task instanceof Task && $this->reads > 1) {
            return $task->withStatus(TaskStatus::now(TaskState::Completed));
        }

        return $task;
    }

    public function all(): array
    {
        return $this->inner->all();
    }

    public function list(ListTasksRequest $request): array
    {
        return $this->inner->list($request);
    }

    public function savePushConfig(TaskPushNotificationConfig $config): void
    {
        $this->inner->savePushConfig($config);
    }

    public function findPushConfig(string $taskId, string $id): ?TaskPushNotificationConfig
    {
        return $this->inner->findPushConfig($taskId, $id);
    }

    public function listPushConfigs(string $taskId): array
    {
        return $this->inner->listPushConfigs($taskId);
    }

    public function deletePushConfig(string $taskId, string $id): void
    {
        $this->inner->deletePushConfig($taskId, $id);
    }
}

/**
 * @return array<string, mixed>
 */
function userMessage(string $messageId, string $text = 'hi', ?string $taskId = null, ?string $contextId = null, ?string $mediaType = null): array
{
    return [
        'messageId' => $messageId,
        'role' => Role::User->value,
        'parts' => [['text' => $text, 'mediaType' => $mediaType]],
        'taskId' => $taskId,
        'contextId' => $contextId,
    ];
}

it('covers protocol helpers, payload, and canonical json', function (): void {
    expect(Protocol::specification())->toBe('1.0.1')
        ->and(Protocol::bindings())->toContain(Protocol::GRPC_BINDING)
        ->and(Payload::string(['n' => 12], 'n'))->toBe('12')
        ->and(Payload::string(['n' => 1.5], 'n'))->toBe('1.5')
        ->and(Payload::int(['n' => '7'], 'n'))->toBe(7)
        ->and(Payload::bool(['ok' => 'true'], 'ok'))->toBeTrue()
        ->and(Payload::bool(['ok' => '1'], 'ok'))->toBeTrue()
        ->and(Payload::bool(['ok' => 'false'], 'ok'))->toBeFalse()
        ->and(Payload::bool(['ok' => '0'], 'ok'))->toBeFalse()
        ->and(Payload::optionalStrings(['tags' => ['a', 1, 'b']], 'tags'))->toBe(['a', 'b'])
        ->and(Payload::string(['task_id' => 'abc'], 'taskId'))->toBe('abc')
        ->and(Payload::int(['history_length' => 1], 'historyLength'))->toBe(1)
        ->and(Payload::bool(['include_artifacts' => true], 'includeArtifacts'))->toBeTrue()
        ->and(CanonicalJson::encode([]))->toBe('[]')
        ->and(CanonicalJson::encode([1, true, null]))->toBe('[1,true,null]')
        ->and(CanonicalJson::encode(false))->toBe('false')
        ->and(CanonicalJson::encode(9))->toBe('9')
        ->and(CanonicalJson::encode(NAN))->toBe('null')
        ->and(CanonicalJson::encode(INF))->toBe('null')
        ->and(CanonicalJson::encode(1.25))->toBe('1.25')
        ->and(CanonicalJson::encode((object) ['z' => 1]))->toContain('z')
        ->and(fn (): ?string => Payload::string(['n' => []], 'n'))->toThrow(InvalidParamsException::class)
        ->and(fn (): string => Payload::requireString(['n' => ''], 'n'))->toThrow(InvalidParamsException::class)
        ->and(fn (): ?int => Payload::int(['n' => true], 'n'))->toThrow(InvalidParamsException::class)
        ->and(fn (): ?bool => Payload::bool(['n' => []], 'n'))->toThrow(InvalidParamsException::class)
        ->and(fn (): ?array => Payload::list(['n' => 'x'], 'n'))->toThrow(InvalidParamsException::class)
        ->and(fn (): ?array => Payload::object(['n' => 'x'], 'n'))->toThrow(InvalidParamsException::class)
        ->and(fn (): array => Payload::requireObject([], 'n'))->toThrow(InvalidParamsException::class)
        ->and(fn (): array => Payload::strings(['n' => [1]], 'n'))->toThrow(InvalidParamsException::class)
        ->and(fn (): array => Payload::mapObjects(['x'], Part::fromArray(...), 'parts'))->toThrow(InvalidParamsException::class)
        ->and(fn (): string => Payload::base64ToBytes('*'))->toThrow(InvalidParamsException::class);
});

it('covers remaining protocol type branches', function (): void {
    expect(fn (): Message => Message::fromArray(['messageId' => 'm', 'role' => 'ROLE_USER', 'parts' => []]))
        ->toThrow(InvalidParamsException::class)
        ->and(fn (): Message => Message::fromArray(['messageId' => 'm', 'role' => 'nope', 'parts' => [['text' => 'x']]]))
        ->toThrow(InvalidParamsException::class)
        ->and(fn (): Artifact => Artifact::fromArray(['artifactId' => 'a', 'parts' => []]))
        ->toThrow(InvalidParamsException::class)
        ->and(fn (): AgentCard => AgentCard::fromArray([
            'name' => 'A',
            'description' => 'B',
            'supportedInterfaces' => [],
            'version' => '1',
            'capabilities' => ['streaming' => true],
            'defaultInputModes' => ['text/plain'],
            'defaultOutputModes' => ['text/plain'],
            'skills' => [],
        ]))->toThrow(InvalidParamsException::class)
        ->and(fn (): AgentCard => AgentCard::fromArray([
            'name' => 'A',
            'description' => 'B',
            'supportedInterfaces' => [['url' => 'https://example.com', 'protocolBinding' => 'JSONRPC', 'protocolVersion' => '1.0']],
            'version' => '1',
            'capabilities' => ['streaming' => true],
            'defaultInputModes' => ['text/plain'],
            'defaultOutputModes' => ['text/plain'],
            'skills' => [],
            'securitySchemes' => ['bad' => 'nope'],
        ]))->toThrow(InvalidParamsException::class)
        ->and(fn (): array => (new Part)->toArray())->toThrow(InvalidParamsException::class)
        ->and(fn (): array => (new SendMessageResponse)->toArray())->toThrow(InvalidParamsException::class)
        ->and(fn (): array => (new StreamResponse)->toArray())->toThrow(InvalidParamsException::class)
        ->and(fn (): StreamResponse => StreamResponse::fromEvent(new stdClass))->toThrow(InvalidParamsException::class)
        ->and(fn (): TaskStatus => TaskStatus::fromArray(['state' => 'nope']))->toThrow(InvalidParamsException::class)
        ->and(fn (): ListTasksRequest => ListTasksRequest::fromArray(['status' => 'nope']))->toThrow(InvalidParamsException::class)
        ->and(fn (): SecurityScheme => SecurityScheme::fromArray([]))->toThrow(InvalidParamsException::class)
        ->and(fn (): SecurityRequirement => SecurityRequirement::fromArray(['schemes' => ['oauth' => 'bad']]))->toThrow(InvalidParamsException::class);

    $requirement = SecurityRequirement::fromArray(['schemes' => ['oauth' => ['list' => ['read', 1]]]]);
    expect($requirement->schemes['oauth'])->toBe(['read'])
        ->and(SecurityRequirement::fromArray(['schemes' => ['api' => ['read']]])->schemes['api'])->toBe(['read']);

    $artifact = new Artifact('a1', [Part::text('one')], 'name');
    $task = new Task('t1', TaskStatus::now(TaskState::Working), 'c1', [$artifact], [new Message('m1', Role::User, [Part::text('hi')])])
        ->withMessage(new Message('m2', Role::Agent, [Part::text('ok')]))
        ->withArtifact(new Artifact('a1', [Part::text('two')], description: 'desc'), true)
        ->withArtifact(new Artifact('a2', [Part::text('other')]), true);

    expect($task->toArrayWithHistory(0, false))->not->toHaveKey('artifacts')
        ->and($task->toArrayWithHistory(1)['history'] ?? [])->toHaveCount(1)
        ->and(SendMessageConfiguration::fromArray([
            'acceptedOutputModes' => ['text/plain'],
            'returnImmediately' => true,
            'historyLength' => 3,
        ])->toArray()['returnImmediately'])->toBeTrue()
        ->and(DeleteTaskPushNotificationConfigRequest::fromArray(['taskId' => 't', 'id' => 'p', 'tenant' => 'acme'])->toArray()['tenant'])->toBe('acme')
        ->and(GetTaskPushNotificationConfigRequest::fromArray(['taskId' => 't', 'id' => 'p', 'tenant' => 'acme'])->toArray()['id'])->toBe('p')
        ->and(ListTaskPushNotificationConfigsRequest::fromArray(['taskId' => 't', 'pageSize' => 2, 'pageToken' => '1'])->toArray()['pageToken'])->toBe('1')
        ->and(GetExtendedAgentCardRequest::fromArray(['tenant' => 'acme'])->toArray()['tenant'])->toBe('acme')
        ->and(SubscribeToTaskRequest::fromArray(['id' => 't', 'tenant' => 'acme'])->toArray()['id'])->toBe('t')
        ->and(TaskArtifactUpdateEvent::fromArray([
            'taskId' => 't',
            'contextId' => 'c',
            'artifact' => ['artifactId' => 'a', 'parts' => [['text' => 'x']]],
            'append' => true,
            'lastChunk' => false,
        ])->toArray()['append'])->toBeTrue();
});

it('covers remaining server routes, stores, and executors', function (): void {
    $app = application();
    $rpc = decode(jsonRpc($app, 'SendMessage', ['message' => userMessage('tck-complete-task-cov')]));
    $taskId = jsonString($rpc, 'result', 'task', 'id');
    $contextId = jsonString($rpc, 'result', 'task', 'contextId');

    expect(jsonAt(decode(jsonRpc($app, 'ListTasks', ['pageSize' => 5])), 'result', 'totalSize'))->toBeGreaterThan(0)
        ->and(jsonString(decode(rest($app, 'GET', '/tasks/'.$taskId)), 'id'))->toBe($taskId)
        ->and(jsonString(decode(rest($app, 'GET', '/extendedAgentCard')), 'name'))->toBe('Test Agent')
        ->and(rest($app, 'POST', '/message:stream', ['message' => userMessage('tck-stream-cov')])->getHeaderLine('Content-Type'))->toBe(Protocol::SSE_CONTENT_TYPE);

    jsonRpc($app, 'CreateTaskPushNotificationConfig', ['url' => 'https://example.com/hook', 'taskId' => $taskId, 'id' => 'p-cov']);
    expect(jsonString(decode(jsonRpc($app, 'GetTaskPushNotificationConfig', ['taskId' => $taskId, 'id' => 'p-cov'])), 'result', 'url'))->toBe('https://example.com/hook')
        ->and(jsonAt(decode(rest($app, 'GET', '/tasks/'.$taskId.'/pushNotificationConfigs')), 'configs'))->not->toBeEmpty();
    jsonRpc($app, 'DeleteTaskPushNotificationConfig', ['taskId' => $taskId, 'id' => 'p-cov']);

    $working = decode(jsonRpc($app, 'SendMessage', ['message' => userMessage('tck-input-required-cov')]));
    $workingId = jsonString($working, 'result', 'task', 'id');
    expect(jsonRpc($app, 'SubscribeToTask', ['id' => $workingId])->getHeaderLine('Content-Type'))->toBe(Protocol::SSE_CONTENT_TYPE);

    foreach ([
        'tck-reject-task-1',
        'tck-artifact-file-url-1',
        'tck-artifact-file-1',
        'tck-artifact-data-1',
        'tck-stream-artifact-chunked-1',
        'tck-stream-artifact-file-1',
        'tck-stream-artifact-text-1',
        'tck-stream-ordering-1',
        'tck-stream-003-1',
        'test-resubscribe-message-id-1',
    ] as $id) {
        expect(jsonRpc($app, 'SendMessage', ['message' => userMessage($id)])->getStatusCode())->toBe(200);
    }

    $rpcApp = application();
    $rpcRequest = new ServerRequest('POST', 'http://127.0.0.1:9999/rpc', [
        'Content-Type' => 'text/plain',
        Protocol::VERSION_HEADER => Protocol::VERSION,
    ], json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'GetExtendedAgentCard', 'params' => []], JSON_THROW_ON_ERROR));
    expect(jsonString(decode($rpcApp->handle($rpcRequest)), 'result', 'name'))->toBe('Test Agent');

    $queryRequest = new ServerRequest('GET', 'http://127.0.0.1:9999/tasks', [
        Protocol::VERSION_HEADER => Protocol::VERSION,
    ])->withQueryParams([
        1 => 'skip',
        'pageSize' => ['10', new stdClass],
        'status' => TaskState::Completed->value,
    ]);
    expect($app->handle($queryRequest)->getStatusCode())->toBe(200)
        ->and(jsonAt(decode($app->handle(new ServerRequest('POST', 'http://127.0.0.1:9999/', [
            'Content-Type' => 'application/json',
            Protocol::VERSION_HEADER => Protocol::VERSION,
        ], '{'))), 'error', 'code'))->toBe(-32700)
        ->and(jsonAt(decode($app->handle(new ServerRequest('POST', 'http://127.0.0.1:9999/', [
            'Content-Type' => 'application/json',
            Protocol::VERSION_HEADER => Protocol::VERSION,
        ], '"nope"'))), 'error', 'code'))->toBe(-32600)
        ->and(jsonAt(decode($app->handle(new ServerRequest('POST', 'http://127.0.0.1:9999/', [
            'Content-Type' => 'application/json',
            Protocol::VERSION_HEADER => Protocol::VERSION,
        ], '{"jsonrpc":"1.0","id":1,"method":"GetTask"}'))), 'error', 'code'))->toBe(-32600)
        ->and(jsonAt(decode($app->handle(new ServerRequest('POST', 'http://127.0.0.1:9999/', [
            'Content-Type' => 'application/json',
            Protocol::VERSION_HEADER => Protocol::VERSION,
        ], '{"jsonrpc":"2.0","id":1,"method":1}'))), 'error', 'code'))->toBe(-32600)
        ->and(jsonAt(decode($app->handle(new ServerRequest('POST', 'http://127.0.0.1:9999/', [
            'Content-Type' => 'application/json',
            Protocol::VERSION_HEADER => Protocol::VERSION,
        ], '{"jsonrpc":"2.0","id":1,"method":"GetTask","params":1}'))), 'error', 'code'))->toBe(-32602)
        ->and(jsonAt(decode(rest($app, 'POST', '/missing')), 'error', 'status'))->toBe('NOT_FOUND')
        ->and(jsonAt(decode(rest($app, 'GET', '/tasks/missing')), 'error', 'status'))->toBe('NOT_FOUND')
        ->and(jsonAt(decode($app->handle(new ServerRequest('POST', 'http://127.0.0.1:9999/message:send', [
            'Content-Type' => Protocol::HTTP_JSON_CONTENT_TYPE,
            Protocol::VERSION_HEADER => Protocol::VERSION,
        ], ''))), 'error', 'code'))->not->toBeNull();

    $bodyDetect = new ServerRequest('PUT', 'http://127.0.0.1:9999/custom', [
        Protocol::VERSION_HEADER => Protocol::VERSION,
    ], '{"jsonrpc":"2.0","id":1,"method":"GetExtendedAgentCard","params":{}}');
    expect(jsonString(decode($app->handle($bodyDetect)), 'result', 'name'))->toBe('Test Agent');

    $named = (new ReflectionMethod(Application::class, 'httpStatusName'));
    expect($named->invoke(application(), 418))->toBe('UNKNOWN')
        ->and($named->invoke(application(), 500))->toBe('INTERNAL')
        ->and($named->invoke(application(), 400))->toBe('INVALID_ARGUMENT');

    $queue = new EventQueue;
    $completing = new CompletingExecutor;
    $completing->execute(new RequestContext(new Message('m', Role::User, [new Part(raw: 'abc')])), $queue);
    $completing->cancel(new RequestContext(new Message('m', Role::User, [Part::text('x')])), $queue);
    $completing->cancel(new RequestContext(new Message('m', Role::User, [Part::text('x')]), new Task('t', TaskStatus::now(TaskState::Working), 'c')), $queue);

    $tck = new TckExecutor;
    $tck->execute(new RequestContext(new Message('plain', Role::User, [Part::text('x')])), $queue);
    $tck->cancel(new RequestContext(new Message('plain', Role::User, [Part::text('x')])), $queue);

    expect(new RequestContext(new Message('m', Role::User, [Part::text('x')], taskId: 'from-message'))->taskId())->toBe('from-message')
        ->and(new RequestContext(new Message('m', Role::User, [Part::text('x')]), new Task('t1', TaskStatus::now(TaskState::Working)))->taskId())->toBe('t1');

    $store = new InMemoryTaskStore;
    $early = new Task('t-early', new TaskStatus(TaskState::Completed, timestamp: '2020-01-01T00:00:00.000Z'), 'ctx-a');
    $late = new Task('t-late', new TaskStatus(TaskState::Working, timestamp: '2024-01-01T00:00:00.000Z'), 'ctx-b');
    $store->save($early);
    $store->save($late);
    expect($store->all())->toHaveCount(2)
        ->and($store->list(new ListTasksRequest(contextId: 'ctx-a')))->toHaveCount(1)
        ->and($store->list(new ListTasksRequest(status: TaskState::Working)))->toHaveCount(1)
        ->and($store->list(new ListTasksRequest(statusTimestampAfter: '2022-01-01T00:00:00.000Z')))->toHaveCount(1)
        ->and($store->findPushConfig('missing', 'x'))->toBeNull();

    $path = sys_get_temp_dir().'/a2a-php-cov-'.Id::uuid().'.json';
    $files = new FileTaskStore($path);
    expect($files->find('missing'))->toBeNull();
    file_put_contents($path, '');
    expect($files->find('missing'))->toBeNull();
    file_put_contents($path, 'null');
    expect($files->all())->toBeEmpty();
    file_put_contents($path, json_encode(['tasks' => 'nope', 'push' => ['t1' => 'nope']], JSON_THROW_ON_ERROR));
    expect($files->listPushConfigs('t1'))->toBeEmpty()
        ->and($files->all())->toBeEmpty();
    $files->save($late);
    $files->savePushConfig(new TaskPushNotificationConfig('https://example.com', id: 'p1', taskId: 't-late'));
    file_put_contents($path, json_encode([
        'tasks' => ['t-late' => $late->toArray(), 'bad' => 'nope'],
        'push' => ['t-late' => ['p1' => ['url' => 'https://example.com', 'id' => 'p1', 'taskId' => 't-late'], 'bad' => 'nope']],
    ], JSON_THROW_ON_ERROR));
    expect($files->list(new ListTasksRequest(contextId: 'ctx-b')))->toHaveCount(1)
        ->and($files->list(new ListTasksRequest(contextId: 'nope')))->toBeEmpty()
        ->and($files->list(new ListTasksRequest(status: TaskState::Working)))->toHaveCount(1)
        ->and($files->list(new ListTasksRequest(status: TaskState::Completed)))->toBeEmpty()
        ->and($files->list(new ListTasksRequest(statusTimestampAfter: '2022-01-01T00:00:00.000Z')))->toHaveCount(1)
        ->and($files->findPushConfig('t-late', 'p1')?->id)->toBe('p1')
        ->and($files->findPushConfig('t-late', 'missing'))->toBeNull()
        ->and($files->findPushConfig('t-late', 'bad'))->toBeNull()
        ->and($files->listPushConfigs('t-late'))->toHaveCount(1);
    file_put_contents($path, json_encode(['tasks' => [], 'push' => ['t-late' => 'nope']], JSON_THROW_ON_ERROR));
    expect($files->findPushConfig('t-late', 'p1'))->toBeNull();
    $files->savePushConfig(new TaskPushNotificationConfig('https://example.com', id: 'p2', taskId: 't-late'));
    chmod($path, 0000);
    set_error_handler(static fn (): bool => true);
    expect($files->find('t-late'))->toBeNull();
    restore_error_handler();
    chmod($path, 0644);
    $decodeTasks = new ReflectionMethod(FileTaskStore::class, 'decodeTasks');
    expect($decodeTasks->invoke(new FileTaskStore($path), 'nope'))->toBe([]);
    unlink($path);

    $dir = sys_get_temp_dir().'/a2a-php-dir-'.Id::uuid();
    mkdir($dir);
    set_error_handler(static fn (): bool => true);
    expect(fn () => new FileTaskStore($dir)->save($late))->toThrow(RuntimeException::class);
    restore_error_handler();
    rmdir($dir);
});

it('covers request handler branches and exception details', function (): void {
    $required = new AgentExtension('https://example.com/ext', 'ext', true);
    $card = new AgentCard(
        name: 'Limited',
        description: 'Limited agent',
        supportedInterfaces: [new AgentInterface('http://127.0.0.1:9999', Protocol::JSON_RPC_BINDING, Protocol::VERSION)],
        version: '0.1.0',
        capabilities: new AgentCapabilities(false, false, [$required], false),
        defaultInputModes: ['text/plain'],
        defaultOutputModes: ['text/plain'],
        skills: [new AgentSkill('echo', 'Echo', 'Echo', ['echo'])],
    );
    $handler = new RequestHandler($card, new CompletingExecutor, new InMemoryTaskStore, subscribeTimeoutSeconds: 0);
    expect(fn () => $handler->assertExtensions([]))->toThrow(ExtensionSupportRequiredException::class);
    $handler->assertExtensions(['https://example.com/ext']);

    $message = new Message('m1', Role::User, [Part::text('hi')]);
    expect(fn (): array => $handler->sendStreamingMessage(new SendMessageRequest($message)))->toThrow(UnsupportedOperationException::class)
        ->and(fn (): TaskPushNotificationConfig => $handler->createPushConfig(new TaskPushNotificationConfig('https://example.com', taskId: 't')))->toThrow(PushNotificationNotSupportedException::class)
        ->and(fn (): AgentCard => $handler->getExtendedAgentCard(new GetExtendedAgentCardRequest))->toThrow(ExtendedAgentCardNotConfiguredException::class)
        ->and(fn (): SendMessageResponse => $handler->sendMessage(new SendMessageRequest(new Message('m2', Role::User, [Part::text('hi', 'image/png')]))))->toThrow(ContentTypeNotSupportedException::class);

    $full = new RequestHandler(agentCard(), new WorkingExecutor, new InMemoryTaskStore, new ThrowingPushSender, agentCard(), 0);
    $created = $full->sendMessage(new SendMessageRequest(
        new Message('m3', Role::User, [Part::text('hi')]),
        configuration: new SendMessageConfiguration(
            taskPushNotificationConfig: new TaskPushNotificationConfig('https://example.com'),
            historyLength: 0,
        ),
    ));
    $task = $created->task;
    expect($task)->toBeInstanceOf(Task::class);

    if (! $task instanceof Task) {
        throw new RuntimeException('Expected a task.');
    }

    expect(fn (): SendMessageResponse => $full->sendMessage(new SendMessageRequest(new Message('m4', Role::User, [Part::text('hi')], contextId: 'other', taskId: $task->id))))
        ->toThrow(InvalidParamsException::class)
        ->and(fn (): TaskPushNotificationConfig => $full->createPushConfig(new TaskPushNotificationConfig('https://example.com')))
        ->toThrow(InvalidParamsException::class)
        ->and(fn (): TaskPushNotificationConfig => $full->getPushConfig(new GetTaskPushNotificationConfigRequest($task->id, 'missing')))
        ->toThrow(TaskNotFoundException::class)
        ->and($full->getTask(new GetTaskRequest($task->id, historyLength: 1))->id)->toBe($task->id)
        ->and($full->listTasks(new ListTasksRequest(pageSize: 1, pageToken: '0', historyLength: 0, includeArtifacts: false))->pageSize)->toBe(1);

    $seen = 0;
    expect($full->subscribeToTask(new SubscribeToTaskRequest($task->id), function () use (&$seen): void {
        $seen++;
    }))->not->toBeEmpty()
        ->and($seen)->toBeGreaterThan(0);

    $activeCalls = 0;
    expect($full->subscribeToTask(new SubscribeToTaskRequest($task->id), isActive: function () use (&$activeCalls): bool {
        $activeCalls++;

        return false;
    }))->toHaveCount(1)
        ->and($activeCalls)->toBeGreaterThan(0)
        ->and($full->cancelTask(new CancelTaskRequest($task->id))->status->state)->toBe(TaskState::Canceled)
        ->and(fn (): array => $full->subscribeToTask(new SubscribeToTaskRequest($task->id)))->toThrow(UnsupportedOperationException::class);

    $empty = new RequestHandler(agentCard(), new EmptyExecutor, new InMemoryTaskStore);
    expect($empty->sendStreamingMessage(new SendMessageRequest(new Message('m-empty', Role::User, [Part::text('hi')]))))->not->toBeEmpty();

    $flip = new RequestHandler(agentCard(), new WorkingExecutor, new FlippingStore, subscribeTimeoutSeconds: 0);
    $flipped = $flip->sendMessage(new SendMessageRequest(new Message('m-flip', Role::User, [Part::text('hi')])))->task;
    expect($flipped)->toBeInstanceOf(Task::class);

    if ($flipped instanceof Task) {
        expect($flip->subscribeToTask(new SubscribeToTaskRequest($flipped->id)))->toHaveCount(2);
    }

    $silent = new RequestHandler(agentCard(), new SilentExecutor, new InMemoryTaskStore);
    $streamed = $silent->sendStreamingMessage(new SendMessageRequest(new Message('m5', Role::User, [Part::text('hi')])));
    expect($streamed)->not->toBeEmpty();
    $silentTask = $silent->sendMessage(new SendMessageRequest(new Message('m6', Role::User, [Part::text('hi')])))->task;
    expect($silentTask)->toBeInstanceOf(Task::class);

    if ($silentTask instanceof Task) {
        expect($silent->cancelTask(new CancelTaskRequest($silentTask->id))->status->state)->toBe(TaskState::Canceled);
    }

    $parse = new ParseException;
    $internal = new InternalErrorException('boom');
    $invalid = new InvalidRequestException;
    expect($parse->errorInfoDetails())->toBeEmpty()
        ->and($internal->jsonRpcCode)->toBe(-32603)
        ->and($invalid->httpStatus)->toBe(400)
        ->and(new VersionNotSupportedException('9')->errorInfoDetails()[0]['reason'] ?? null)->toBe('VERSION_NOT_SUPPORTED')
        ->and(new InternalErrorException('x')->errorInfoDetails())->toBeEmpty();
});

it('covers remaining client, card, and error mapping paths', function (): void {
    $taskPayload = ['task' => new Task('t1', TaskStatus::now(TaskState::Completed), 'c1')->toArray()];
    $responses = [
        new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => '1', 'result' => $taskPayload['task']], JSON_THROW_ON_ERROR)),
        new Response(200, ['Content-Type' => Protocol::SSE_CONTENT_TYPE], 'data: {"result":{"task":'.json_encode($taskPayload['task'], JSON_THROW_ON_ERROR)."}}\n\n"),
        new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => '1', 'result' => ['tasks' => [], 'nextPageToken' => '', 'pageSize' => 50, 'totalSize' => 0]], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => '1', 'result' => $taskPayload['task']], JSON_THROW_ON_ERROR)),
        new Response(200, ['Content-Type' => Protocol::SSE_CONTENT_TYPE], "data: {\"result\":{\"statusUpdate\":{\"taskId\":\"t1\",\"contextId\":\"c1\",\"status\":{\"state\":\"TASK_STATE_WORKING\"}}}}\n\n"),
        new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => '1', 'result' => ['url' => 'https://example.com', 'id' => 'p1', 'taskId' => 't1']], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => '1', 'result' => ['url' => 'https://example.com', 'id' => 'p1', 'taskId' => 't1']], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => '1', 'result' => ['configs' => [], 'nextPageToken' => '']], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => '1', 'result' => 'ok'], JSON_THROW_ON_ERROR)),
        new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => '1', 'result' => agentCard()->toArray()], JSON_THROW_ON_ERROR)),
        new Response(200, [], '{'),
        new Response(200, [], 'true'),
        new Response(200, ['Content-Type' => Protocol::SSE_CONTENT_TYPE], 'data: {"result":{"task":'.json_encode($taskPayload['task'], JSON_THROW_ON_ERROR).'}}'),
        new Response(200, ['Content-Type' => Protocol::SSE_CONTENT_TYPE], "data: {\"result\":\"bad\"}\n\n"),
    ];
    $http = new FakeHttp($responses);
    $rpc = new JsonRpcClient($http, Psr17FactoryDiscovery::findRequestFactory(), Psr17FactoryDiscovery::findStreamFactory(), 'http://127.0.0.1:9999');
    expect($rpc->getTask(new GetTaskRequest('t1'))->id)->toBe('t1')
        ->and($rpc->sendStreamingMessage(new SendMessageRequest(new Message('m', Role::User, [Part::text('hi')]))))->not->toBeEmpty()
        ->and($rpc->listTasks(new ListTasksRequest)->totalSize)->toBe(0)
        ->and($rpc->cancelTask(new CancelTaskRequest('t1'))->id)->toBe('t1')
        ->and($rpc->subscribeToTask(new SubscribeToTaskRequest('t1')))->not->toBeEmpty()
        ->and($rpc->createTaskPushNotificationConfig(new TaskPushNotificationConfig('https://example.com', taskId: 't1'))->id)->toBe('p1')
        ->and($rpc->getTaskPushNotificationConfig(new GetTaskPushNotificationConfigRequest('t1', 'p1'))->id)->toBe('p1')
        ->and($rpc->listTaskPushNotificationConfigs(new ListTaskPushNotificationConfigsRequest('t1'))->configs)->toBeEmpty();
    $rpc->deleteTaskPushNotificationConfig(new DeleteTaskPushNotificationConfigRequest('t1', 'p1'));
    expect($rpc->getExtendedAgentCard(new GetExtendedAgentCardRequest)->name)->toBe('Test Agent')
        ->and(fn (): Task => $rpc->getTask(new GetTaskRequest('t1')))->toThrow(InvalidAgentResponseException::class)
        ->and(fn (): Task => $rpc->getTask(new GetTaskRequest('t1')))->toThrow(InvalidAgentResponseException::class)
        ->and($rpc->sendStreamingMessage(new SendMessageRequest(new Message('m', Role::User, [Part::text('hi')]))))->not->toBeEmpty()
        ->and(fn (): array => $rpc->sendStreamingMessage(new SendMessageRequest(new Message('m', Role::User, [Part::text('hi')]))))->toThrow(InvalidAgentResponseException::class);

    $codes = [
        ['code' => -32700],
        ['code' => '-32601'],
        ['code' => -32602],
        ['code' => -32603],
        ['code' => -32002],
        ['code' => -32003],
        ['code' => -32004],
        ['code' => -32005],
        ['code' => -32006],
        ['code' => -32007],
        ['code' => -32008],
        ['code' => -32009],
        ['code' => 400, 'data' => [['reason' => 'TASK_NOT_FOUND']]],
        ['code' => 400, 'details' => [['reason' => 'TASK_NOT_CANCELABLE']]],
        ['code' => 400, 'data' => [['reason' => 'PUSH_NOTIFICATION_NOT_SUPPORTED']]],
        ['code' => 400, 'data' => [['reason' => 'UNSUPPORTED_OPERATION']]],
        ['code' => 400, 'data' => [['reason' => 'CONTENT_TYPE_NOT_SUPPORTED']]],
        ['code' => 400, 'data' => [['reason' => 'INVALID_AGENT_RESPONSE']]],
        ['code' => 400, 'data' => [['reason' => 'EXTENDED_AGENT_CARD_NOT_CONFIGURED']]],
        ['code' => 400, 'data' => [['reason' => 'EXTENSION_SUPPORT_REQUIRED']]],
        ['code' => 400, 'data' => [['reason' => 'VERSION_NOT_SUPPORTED']]],
        ['code' => 400, 'data' => 'nope'],
        ['code' => true],
        ['code' => 404],
        ['code' => 999],
    ];
    $errorResponses = array_map(
        fn (array $error): Response => new Response(400, [], json_encode(['error' => $error], JSON_THROW_ON_ERROR)),
        $codes,
    );
    $errorResponses[] = new Response(404, [], json_encode(['error' => ['code' => 400, 'message' => 'gone']], JSON_THROW_ON_ERROR));
    $errors = new FakeHttp($errorResponses);
    $json = new HttpJsonClient($errors, Psr17FactoryDiscovery::findRequestFactory(), Psr17FactoryDiscovery::findStreamFactory(), 'http://127.0.0.1:9999');

    $mapped = 0;

    foreach ($codes as $error) {
        try {
            $json->getTask(new GetTaskRequest('t1'));
        } catch (Throwable) {
            $mapped++;
        }
    }

    expect($mapped)->toBe(count($codes));

    try {
        $json->getTask(new GetTaskRequest('t1'));
    } catch (TaskNotFoundException) {
        // HTTP 404 with reason-less 400 maps through fromReason.
    }

    $factory = new ClientFactory;
    expect($factory->forCard(agentCard()))->toBeInstanceOf(JsonRpcClient::class)
        ->and(ClientFactory::create(new FakeHttp([new Response(200, [], json_encode(['task' => $taskPayload['task']], JSON_THROW_ON_ERROR))]))->forCard(agentCard(), Auth::none(), Protocol::HTTP_JSON_BINDING)->sendMessage(new SendMessageRequest(new Message('m', Role::User, [Part::text('hi')])))->task?->id)->toBe('t1')
        ->and(fn (): AgentCard => new AgentCardResolver(new FakeHttp([new Response(200, [], '"nope"')]), Psr17FactoryDiscovery::findRequestFactory())->resolve('http://example.com'))->toThrow(InvalidAgentResponseException::class);

    $unsigned = agentCard();
    expect((new AgentCardVerifier)->verify($unsigned, openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]) ?: throw new RuntimeException('key')))->toBeFalse();

    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    $details = openssl_pkey_get_details($key);
    $pem = is_array($details) ? $details['key'] ?? null : null;

    if (! is_string($pem)) {
        throw new RuntimeException('Unable to export the public key.');
    }

    $public = openssl_pkey_get_public($pem);

    if ($public === false) {
        throw new RuntimeException('Unable to load the public key.');
    }

    $bad = new AgentCard(
        $unsigned->name,
        $unsigned->description,
        $unsigned->supportedInterfaces,
        $unsigned->version,
        $unsigned->capabilities,
        $unsigned->defaultInputModes,
        $unsigned->defaultOutputModes,
        $unsigned->skills,
        signatures: [new AgentCardSignature('abc', '!!!')],
    );
    expect((new AgentCardVerifier)->verify($bad, $public))->toBeFalse();
    set_error_handler(static fn (): bool => true);
    expect(fn (): AgentCardSignature => (new AgentCardSigner)->sign($unsigned, $public, ['alg' => 'RS256']))->toThrow(RuntimeException::class);
    restore_error_handler();

    $sender = new HttpPushNotificationSender(new FakeHttp([new Response(200, [], '{}')]), Psr17FactoryDiscovery::findRequestFactory(), Psr17FactoryDiscovery::findStreamFactory());
    $sender->send(new TaskPushNotificationConfig('https://example.com'), StreamResponse::fromEvent(new Task('t1', TaskStatus::now(TaskState::Completed), 'c1')));
});
