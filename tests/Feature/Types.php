<?php

declare(strict_types=1);

use TiborSrc\A2A\Json\CanonicalJson;
use TiborSrc\A2A\Json\Payload;
use TiborSrc\A2A\Types\AgentCapabilities;
use TiborSrc\A2A\Types\AgentCard;
use TiborSrc\A2A\Types\AgentCardSignature;
use TiborSrc\A2A\Types\AgentExtension;
use TiborSrc\A2A\Types\AgentInterface;
use TiborSrc\A2A\Types\AgentProvider;
use TiborSrc\A2A\Types\AgentSkill;
use TiborSrc\A2A\Types\APIKeySecurityScheme;
use TiborSrc\A2A\Types\AuthorizationCodeOAuthFlow;
use TiborSrc\A2A\Types\ClientCredentialsOAuthFlow;
use TiborSrc\A2A\Types\DeviceCodeOAuthFlow;
use TiborSrc\A2A\Types\HTTPAuthSecurityScheme;
use TiborSrc\A2A\Types\ImplicitOAuthFlow;
use TiborSrc\A2A\Types\ListTasksResponse;
use TiborSrc\A2A\Types\Message;
use TiborSrc\A2A\Types\MutualTlsSecurityScheme;
use TiborSrc\A2A\Types\OAuth2SecurityScheme;
use TiborSrc\A2A\Types\OAuthFlows;
use TiborSrc\A2A\Types\OpenIdConnectSecurityScheme;
use TiborSrc\A2A\Types\Part;
use TiborSrc\A2A\Types\PasswordOAuthFlow;
use TiborSrc\A2A\Types\Role;
use TiborSrc\A2A\Types\SecurityRequirement;
use TiborSrc\A2A\Types\SecurityScheme;
use TiborSrc\A2A\Types\SendMessageConfiguration;
use TiborSrc\A2A\Types\SendMessageRequest;
use TiborSrc\A2A\Types\StreamResponse;
use TiborSrc\A2A\Types\Task;
use TiborSrc\A2A\Types\TaskPushNotificationConfig;
use TiborSrc\A2A\Types\TaskState;
use TiborSrc\A2A\Types\TaskStatus;
use TiborSrc\A2A\Types\TaskStatusUpdateEvent;

it('round-trips core protocol objects', function (): void {
    $part = Part::fromArray(['text' => 'hello', 'mediaType' => 'text/plain']);
    $file = Part::fromArray(['raw' => Payload::bytesToBase64('abc'), 'filename' => 'a.txt']);
    $message = Message::fromArray([
        'messageId' => 'm1',
        'role' => Role::User->value,
        'parts' => [$part->toArray(), $file->toArray()],
        'extensions' => ['https://example.com/ext'],
    ]);
    $task = Task::fromArray([
        'id' => 't1',
        'contextId' => 'c1',
        'status' => TaskStatus::now(TaskState::Working)->toArray(),
        'history' => [$message->toArray()],
        'artifacts' => [['artifactId' => 'a1', 'parts' => [['text' => 'out']]]],
    ]);

    $streamed = StreamResponse::fromEvent($task)->toArray();

    expect(Task::fromArray($task->toArray())->id)->toBe('t1')
        ->and($task->withStatus(TaskStatus::now(TaskState::Completed))->status->state)->toBe(TaskState::Completed)
        ->and(jsonString($streamed, 'task', 'id'))->toBe('t1')
        ->and(TaskState::InputRequired->isInterrupted())->toBeTrue()
        ->and(TaskState::AuthRequired->isInterrupted())->toBeTrue()
        ->and(TaskState::Working->isInterrupted())->toBeFalse();
});

it('round-trips agent cards and security schemes', function (): void {
    $card = new AgentCard(
        name: 'Agent',
        description: 'Desc',
        supportedInterfaces: [new AgentInterface('https://example.com', 'JSONRPC', '1.0', 'tenant-1')],
        version: '1.2.0',
        capabilities: new AgentCapabilities(true, false, [new AgentExtension('https://example.com/ext', 'ext', true, ['a' => 'b'])], true),
        defaultInputModes: ['text/plain'],
        defaultOutputModes: ['text/plain'],
        skills: [new AgentSkill('s1', 'Skill', 'Does things', ['tag'], ['example'], ['text/plain'], ['text/plain'], [new SecurityRequirement(['oauth' => ['read']])])],
        provider: new AgentProvider('https://provider.example', 'Org'),
        documentationUrl: 'https://docs.example',
        securitySchemes: [
            'api' => new SecurityScheme(apiKeySecurityScheme: new APIKeySecurityScheme('header', 'X-Key', 'desc')),
            'http' => new SecurityScheme(httpAuthSecurityScheme: new HTTPAuthSecurityScheme('Bearer', 'desc', 'JWT')),
            'oidc' => new SecurityScheme(openIdConnectSecurityScheme: new OpenIdConnectSecurityScheme('https://id.example/.well-known/openid-configuration')),
            'mtls' => new SecurityScheme(mtlsSecurityScheme: new MutualTlsSecurityScheme('mtls')),
            'oauth' => new SecurityScheme(oauth2SecurityScheme: new OAuth2SecurityScheme(new OAuthFlows(
                authorizationCode: new AuthorizationCodeOAuthFlow('https://auth', 'https://token', ['read' => 'Read'], 'https://refresh', true),
                clientCredentials: new ClientCredentialsOAuthFlow('https://token', ['read' => 'Read']),
                implicit: new ImplicitOAuthFlow('https://auth', ['read' => 'Read']),
                password: new PasswordOAuthFlow('https://token', ['read' => 'Read']),
                deviceCode: new DeviceCodeOAuthFlow('https://device', 'https://token', ['read' => 'Read']),
            ), 'oauth', 'https://meta')),
        ],
        securityRequirements: [new SecurityRequirement(['oauth' => ['read']])],
        signatures: [new AgentCardSignature('abc', 'sig', ['kid' => '1'])],
        iconUrl: 'https://example.com/icon.png',
    );

    $decoded = AgentCard::fromArray($card->toArray());

    expect($decoded->name)->toBe('Agent')
        ->and($decoded->preferredInterface('HTTP+JSON')?->protocolBinding)->toBe('JSONRPC')
        ->and($decoded->acceptedInputModes())->toContain('text/plain')
        ->and(CanonicalJson::encode(['b' => 1, 'a' => true]))->toBe('{"a":true,"b":1}');
});

it('parses send-message configuration and list responses', function (): void {
    $request = SendMessageRequest::fromArray([
        'message' => [
            'messageId' => 'm1',
            'role' => 'ROLE_USER',
            'parts' => [['text' => 'hi']],
        ],
        'configuration' => [
            'acceptedOutputModes' => ['text/plain'],
            'historyLength' => 2,
            'returnImmediately' => true,
            'taskPushNotificationConfig' => [
                'url' => 'https://example.com/hook',
            ],
        ],
    ]);

    $pushConfig = $request->configuration?->taskPushNotificationConfig;

    expect($request->configuration)->toBeInstanceOf(SendMessageConfiguration::class)
        ->and($pushConfig)->toBeInstanceOf(TaskPushNotificationConfig::class);

    if (! $pushConfig instanceof TaskPushNotificationConfig) {
        throw new RuntimeException('Expected a push notification config.');
    }

    expect(TaskPushNotificationConfig::fromArray($pushConfig->toArray())->url)->toBe('https://example.com/hook')
        ->and(TaskPushNotificationConfig::fromArray([
            'task_id' => 't-nested',
            'config' => ['url' => 'https://example.com/nested', 'id' => 'cfg-1'],
        ])->taskId)->toBe('t-nested')
        ->and(ListTasksResponse::fromArray(['tasks' => [], 'nextPageToken' => '', 'pageSize' => 50, 'totalSize' => 0])->totalSize)->toBe(0);

    $finalUpdate = TaskStatusUpdateEvent::fromArray([
        'taskId' => 't1',
        'contextId' => 'c1',
        'status' => TaskStatus::now(TaskState::Completed)->toArray(),
        'final' => true,
    ]);

    expect($finalUpdate->final)->toBeTrue()
        ->and($finalUpdate->toArray())->not->toHaveKey('final');
});
