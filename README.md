# tibor-src/a2a-php

PHP SDK for the [Agent2Agent (A2A) protocol](https://a2a-protocol.org/latest/specification/), targeting the latest released specification **v1.0.1** (protocol version `1.0`).

The package follows [nunomaduro/skeleton-php](https://github.com/nunomaduro/skeleton-php): `declare(strict_types=1)`, `final` classes, Pest, PHPStan at max, Pint, Rector, and 100% type coverage.

## Requirements

- PHP 8.5+
- A PSR-18 HTTP client and PSR-17 factories (discovered automatically, or inject your own)

## Installation

```bash
composer require tibor-src/a2a-php
```

## Client quick start

```php
use TiborSrc\A2A\Client\ClientFactory;
use TiborSrc\A2A\Types\Message;
use TiborSrc\A2A\Types\Part;
use TiborSrc\A2A\Types\Role;
use TiborSrc\A2A\Types\SendMessageRequest;

$client = ClientFactory::create()->connect('https://agent.example.com');

$response = $client->sendMessage(new SendMessageRequest(
    new Message(
        messageId: '11111111-1111-4111-8111-111111111111',
        role: Role::User,
        parts: [Part::text('Hello')],
    ),
));

echo $response->task?->status->state->value;
```

JSON-RPC 2.0 and HTTP+JSON/REST are implemented. gRPC is not implemented in v0.1.0: PHP has no first-class A2A gRPC stack in this package, and it is listed as a Composer `suggest` only.

## Server quick start

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use TiborSrc\A2A\Protocol;
use TiborSrc\A2A\Server\Application;
use TiborSrc\A2A\Server\CompletingExecutor;
use TiborSrc\A2A\Server\InMemoryTaskStore;
use TiborSrc\A2A\Server\RequestHandler;
use TiborSrc\A2A\Types\AgentCapabilities;
use TiborSrc\A2A\Types\AgentCard;
use TiborSrc\A2A\Types\AgentInterface;
use TiborSrc\A2A\Types\AgentSkill;

$card = new AgentCard(
    name: 'Echo',
    description: 'Echoes user text.',
    supportedInterfaces: [
        new AgentInterface('http://127.0.0.1:9999', Protocol::JSON_RPC_BINDING, Protocol::VERSION),
        new AgentInterface('http://127.0.0.1:9999', Protocol::HTTP_JSON_BINDING, Protocol::VERSION),
    ],
    version: '0.1.0',
    capabilities: new AgentCapabilities(streaming: true, pushNotifications: true),
    defaultInputModes: ['text/plain'],
    defaultOutputModes: ['text/plain'],
    skills: [new AgentSkill('echo', 'Echo', 'Echoes text', ['echo'])],
);

$factory = new Psr17Factory;
$application = new Application(
    new RequestHandler($card, new CompletingExecutor, new InMemoryTaskStore),
    $factory,
    $factory,
);
```

`$application` is a PSR-15 handler. Mount it in any framework, or run the example:

```bash
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:9999 examples/server.php
```

## Conformance

Official [a2a-tck](https://github.com/a2aproject/a2a-tck) against `examples/server.php`, spec media type **1.0.1**, transports `jsonrpc,http_json`.

| Binding | MUST | SHOULD | Notes |
| --- | --- | --- | --- |
| JSON-RPC | pass (83/90 automated, 7 skipped) | pass (6/6) | `POST /` and `POST /rpc` |
| HTTP+JSON | pass (80/85 automated, 5 skipped) | pass (7/7) | `application/a2a+json` |
| gRPC | not supported | not supported | Optional; Composer `suggest` only |

MUST overall: **81 passed, 0 failed, 11 skipped**, plus 7 manual checks the TCK cannot automate (client TLS, card signing observation, custom binding versioning). SHOULD overall: **9 passed, 0 failed, 1 skipped**, plus 2 manual TLS checks.

Run the official TCK against the example server:

```bash
php -S 127.0.0.1:9999 examples/server.php
# elsewhere
git clone https://github.com/a2aproject/a2a-tck.git
cd a2a-tck && uv venv && uv pip install -e .
uv run ./run_tck.py --sut-host http://127.0.0.1:9999 --transport jsonrpc,http_json
```

## Spec coverage

Implemented: Task, TaskStatus/TaskState, Message, Part (text/file/data), Artifact, Agent Card (including signatures), skills, capabilities, security schemes, extensions, push notification config CRUD, JSON-RPC and HTTP+JSON bindings, SSE streaming, Agent Card discovery, extended Agent Card.

Not supported: gRPC transport (would require the `grpc` extension and a generated protobuf stub; deferred so the package stays framework-agnostic and testable without native gRPC).

## Development

```bash
composer install
composer test
composer test:tck
```

## License

MIT. See [LICENSE.md](LICENSE.md).
