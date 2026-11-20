<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TiborSrc\A2A\Protocol;
use TiborSrc\A2A\Server\Application;
use TiborSrc\A2A\Server\InMemoryTaskStore;
use TiborSrc\A2A\Server\RequestHandler;
use TiborSrc\A2A\Server\TckExecutor;
use TiborSrc\A2A\Types\AgentCapabilities;
use TiborSrc\A2A\Types\AgentCard;
use TiborSrc\A2A\Types\AgentInterface;
use TiborSrc\A2A\Types\AgentSkill;

final class FakeHttp implements ClientInterface
{
    /**
     * @param  list<ResponseInterface>  $responses
     * @param  list<RequestInterface>  $requests
     */
    public function __construct(
        private array $responses,
        public array $requests = [],
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return array_shift($this->responses) ?? new Response(500, [], '{}');
    }
}

function agentCard(): AgentCard
{
    return new AgentCard(
        name: 'Test Agent',
        description: 'Unit test agent',
        supportedInterfaces: [
            new AgentInterface('http://127.0.0.1:9999', Protocol::JSON_RPC_BINDING, Protocol::VERSION),
            new AgentInterface('http://127.0.0.1:9999', Protocol::HTTP_JSON_BINDING, Protocol::VERSION),
        ],
        version: '0.1.0',
        capabilities: new AgentCapabilities(true, true, [], true),
        defaultInputModes: ['text/plain', 'application/json'],
        defaultOutputModes: ['text/plain'],
        skills: [new AgentSkill('echo', 'Echo', 'Echo', ['echo'])],
    );
}

function application(?RequestHandler $handler = null): Application
{
    $factory = new Psr17Factory;
    $handler ??= new RequestHandler(agentCard(), new TckExecutor, new InMemoryTaskStore, subscribeTimeoutSeconds: 0);

    return new Application($handler, $factory, $factory);
}

/**
 * @param  array<string, mixed>  $params
 */
function jsonRpc(Application $app, string $method, array $params = [], mixed $id = 1): ResponseInterface
{
    $request = new ServerRequest('POST', 'http://127.0.0.1:9999/', [
        'Content-Type' => 'application/json',
        Protocol::VERSION_HEADER => Protocol::VERSION,
    ], json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'method' => $method,
        'params' => $params,
    ], JSON_THROW_ON_ERROR));

    return $app->handle($request);
}

/**
 * @param  array<string, mixed>|null  $body
 * @param  array<string, scalar>  $query
 */
function rest(Application $app, string $method, string $path, ?array $body = null, array $query = []): ResponseInterface
{
    $uri = 'http://127.0.0.1:9999'.$path;

    if ($query !== []) {
        $uri .= '?'.http_build_query($query);
    }

    $request = new ServerRequest($method, $uri, [
        'Content-Type' => Protocol::HTTP_JSON_CONTENT_TYPE,
        Protocol::VERSION_HEADER => Protocol::VERSION,
    ], $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR));

    return $app->handle($request);
}

/**
 * @return array<string, mixed>
 */
function decode(ResponseInterface $response): array
{
    $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($payload)) {
        throw new RuntimeException('Expected a JSON object.');
    }

    $decoded = [];

    foreach ($payload as $key => $value) {
        $decoded[(string) $key] = $value;
    }

    return $decoded;
}

/**
 * @param  array<mixed>  $data
 */
function jsonAt(array $data, int|string ...$path): mixed
{
    $current = $data;

    foreach ($path as $key) {
        if (! is_array($current) || ! array_key_exists($key, $current)) {
            throw new RuntimeException('Missing JSON path: '.implode('.', array_map(strval(...), $path)));
        }

        $current = $current[$key];
    }

    return $current;
}

/**
 * @param  array<mixed>  $data
 */
function jsonString(array $data, int|string ...$path): string
{
    $value = jsonAt($data, ...$path);

    if (is_string($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    throw new RuntimeException('Expected a string at JSON path.');
}
