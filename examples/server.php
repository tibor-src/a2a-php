<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;
use TiborSrc\A2A\Exceptions\A2AException;
use TiborSrc\A2A\Protocol;
use TiborSrc\A2A\Server\Application;
use TiborSrc\A2A\Server\FileTaskStore;
use TiborSrc\A2A\Server\HttpPushNotificationSender;
use TiborSrc\A2A\Server\RequestHandler;
use TiborSrc\A2A\Server\TckExecutor;
use TiborSrc\A2A\Types\AgentCapabilities;
use TiborSrc\A2A\Types\AgentCard;
use TiborSrc\A2A\Types\AgentInterface;
use TiborSrc\A2A\Types\AgentSkill;
use TiborSrc\A2A\Types\StreamResponse;
use TiborSrc\A2A\Types\SubscribeToTaskRequest;

require dirname(__DIR__).'/vendor/autoload.php';

$base = getenv('A2A_BASE_URL') ?: 'http://127.0.0.1:9999';
$storePath = getenv('A2A_STORE') ?: sys_get_temp_dir().'/a2a-php-tck-store.json';

$card = new AgentCard(
    name: 'TCK PHP Agent',
    description: 'A2A v1.0 example agent used for conformance testing.',
    supportedInterfaces: [
        new AgentInterface($base, Protocol::JSON_RPC_BINDING, Protocol::VERSION),
        new AgentInterface($base, Protocol::HTTP_JSON_BINDING, Protocol::VERSION),
    ],
    version: '0.1.0',
    capabilities: new AgentCapabilities(streaming: true, pushNotifications: true, extendedAgentCard: true),
    defaultInputModes: ['text/plain', 'application/json'],
    defaultOutputModes: ['text/plain', 'application/json'],
    skills: [
        new AgentSkill('echo', 'Echo', 'Echoes TCK scenarios based on messageId prefixes.', ['tck', 'echo']),
    ],
);

$factory = new Psr17Factory;
$handler = new RequestHandler(
    agentCard: $card,
    executor: new TckExecutor,
    store: new FileTaskStore($storePath),
    pushSender: new HttpPushNotificationSender(new Client(['timeout' => 2, 'connect_timeout' => 1]), $factory, $factory),
    extendedAgentCard: $card,
);

$app = new Application($handler, $factory, $factory);
$creator = new ServerRequestCreator($factory, $factory, $factory, $factory);
$request = $creator->fromGlobals();

if (streamSubscribe($handler, $request)) {
    exit;
}

$response = $app->handle($request);

http_response_code($response->getStatusCode());

foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header($name.': '.$value, false);
    }
}

echo (string) $response->getBody();

function streamSubscribe(RequestHandler $handler, ServerRequestInterface $request): bool
{
    $path = $request->getUri()->getPath();
    $path = $path === '' ? '/' : $path;
    $body = (string) $request->getBody();
    $request->getBody()->rewind();
    $payload = $body === '' ? [] : json_decode($body, true);
    $jsonRpc = is_array($payload) && ($payload['jsonrpc'] ?? null) === '2.0';
    $jsonRpcId = is_array($payload) ? ($payload['id'] ?? null) : null;
    $taskId = null;

    if ($jsonRpc && ($payload['method'] ?? null) === 'SubscribeToTask') {
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
        $taskId = is_string($params['id'] ?? null) ? $params['id'] : (is_string($params['task_id'] ?? null) ? $params['task_id'] : null);
    }

    if (preg_match('#^/tasks/([^/]+):subscribe$#', $path, $matches) === 1 && in_array($request->getMethod(), ['GET', 'POST'], true)) {
        $taskId = urldecode($matches[1]);
        $jsonRpc = false;
    }

    if (! is_string($taskId) || $taskId === '') {
        return false;
    }

    $started = false;

    try {
        $version = $request->getHeaderLine(Protocol::VERSION_HEADER);
        $handler->assertVersion($version === '' ? null : $version);
        $handler->subscribeToTask(new SubscribeToTaskRequest($taskId), function (StreamResponse $event) use (&$started, $jsonRpc, $jsonRpcId): void {
            if (! $started) {
                ignore_user_abort(true);
                http_response_code(200);
                header('Content-Type: '.Protocol::SSE_CONTENT_TYPE);
                header('Cache-Control: no-cache');

                while (ob_get_level() > 0) {
                    ob_end_flush();
                }

                flush();
                $started = true;
            }

            $data = $event->toArray();

            if ($jsonRpc) {
                $data = [
                    'jsonrpc' => '2.0',
                    'id' => $jsonRpcId,
                    'result' => $data,
                ];
            }

            echo 'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";
            flush();
        });
    } catch (A2AException) {
        return $started;
    }

    return $started;
}
