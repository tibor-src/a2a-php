<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TiborSrc\A2A\Protocol;
use TiborSrc\A2A\Types\AuthenticationInfo;
use TiborSrc\A2A\Types\StreamResponse;
use TiborSrc\A2A\Types\TaskPushNotificationConfig;

final readonly class HttpPushNotificationSender implements PushNotificationSender
{
    public function __construct(
        private ClientInterface $http,
        private RequestFactoryInterface $requests,
        private StreamFactoryInterface $streams,
    ) {}

    public function send(TaskPushNotificationConfig $config, StreamResponse $payload): void
    {
        $body = json_encode($payload->toArray(), JSON_THROW_ON_ERROR);
        $request = $this->requests
            ->createRequest('POST', $config->url)
            ->withHeader('Content-Type', Protocol::HTTP_JSON_CONTENT_TYPE)
            ->withBody($this->streams->createStream($body));

        if ($config->authentication instanceof AuthenticationInfo) {
            $scheme = $config->authentication->scheme;
            $credentials = $config->authentication->credentials ?? '';
            $request = $request->withHeader('Authorization', trim($scheme.' '.$credentials));
        }

        if ($config->token !== null && $config->token !== '') {
            $request = $request->withHeader('X-A2A-Notification-Token', $config->token);
        }

        $this->http->sendRequest($request);
    }
}
