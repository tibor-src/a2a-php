<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TiborSrc\A2A\Exceptions\A2AException;
use TiborSrc\A2A\Exceptions\ContentTypeNotSupportedException;
use TiborSrc\A2A\Exceptions\ExtendedAgentCardNotConfiguredException;
use TiborSrc\A2A\Exceptions\ExtensionSupportRequiredException;
use TiborSrc\A2A\Exceptions\InternalErrorException;
use TiborSrc\A2A\Exceptions\InvalidAgentResponseException;
use TiborSrc\A2A\Exceptions\InvalidParamsException;
use TiborSrc\A2A\Exceptions\InvalidRequestException;
use TiborSrc\A2A\Exceptions\MethodNotFoundException;
use TiborSrc\A2A\Exceptions\ParseException;
use TiborSrc\A2A\Exceptions\PushNotificationNotSupportedException;
use TiborSrc\A2A\Exceptions\TaskNotCancelableException;
use TiborSrc\A2A\Exceptions\TaskNotFoundException;
use TiborSrc\A2A\Exceptions\UnsupportedOperationException;
use TiborSrc\A2A\Exceptions\VersionNotSupportedException;
use TiborSrc\A2A\Protocol;
use TiborSrc\A2A\Types\StreamResponse;

abstract class HttpTransport
{
    protected readonly Auth $auth;

    public function __construct(
        protected readonly ClientInterface $http,
        protected readonly RequestFactoryInterface $requests,
        protected readonly StreamFactoryInterface $streams,
        protected readonly string $baseUrl,
        ?Auth $auth = null,
        protected readonly string $protocolVersion = Protocol::VERSION,
    ) {
        $this->auth = $auth ?? Auth::none();
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function send(string $method, string $url, ?string $body, array $headers): ResponseInterface
    {
        $request = $this->requests->createRequest($method, $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $request = $request
            ->withHeader(Protocol::VERSION_HEADER, $this->protocolVersion)
            ->withBody($this->streams->createStream($body ?? ''));

        return $this->http->sendRequest($this->auth->apply($request));
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(ResponseInterface $response): array
    {
        $contents = (string) $response->getBody();

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            throw new InvalidAgentResponseException($jsonException->getMessage());
        }

        if (! is_array($decoded)) {
            throw new InvalidAgentResponseException('Expected a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function decodeSse(ResponseInterface $response): array
    {
        $events = [];
        $buffer = '';

        foreach (preg_split("/\r\n|\n|\r/", (string) $response->getBody()) ?: [] as $line) {
            if (str_starts_with($line, 'data:')) {
                $buffer .= trim(substr($line, 5));

                continue;
            }

            if ($line === '' && $buffer !== '') {
                $decoded = json_decode($buffer, true);
                $buffer = '';

                if (is_array($decoded)) {
                    /** @var array<string, mixed> $decoded */
                    $events[] = $decoded;
                }
            }
        }

        if ($buffer !== '') {
            $decoded = json_decode($buffer, true);

            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $events[] = $decoded;
            }
        }

        return $events;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<StreamResponse>
     */
    protected function streamResponses(array $events, bool $jsonRpc): array
    {
        $responses = [];

        foreach ($events as $event) {
            $payload = $jsonRpc ? ($event['result'] ?? $event) : $event;

            if (! is_array($payload)) {
                throw new InvalidAgentResponseException('Invalid stream event.');
            }

            $decoded = [];

            foreach ($payload as $key => $value) {
                $decoded[(string) $key] = $value;
            }

            $responses[] = StreamResponse::fromArray($decoded);
        }

        return $responses;
    }

    /**
     * @param  array<string, mixed>  $error
     */
    protected function throwError(array $error, int $httpStatus = 0): never
    {
        $rawCode = $error['code'] ?? $httpStatus;
        $message = is_string($error['message'] ?? null) ? $error['message'] : 'A2A error';
        $code = match (true) {
            is_int($rawCode) => $rawCode,
            is_string($rawCode) && is_numeric($rawCode) => (int) $rawCode,
            default => $httpStatus,
        };

        throw match ($code) {
            -32700 => new ParseException($message),
            -32600, 400 => $this->fromReason($error, $message, $httpStatus === 404 ? 404 : $code),
            -32601 => new MethodNotFoundException($message),
            -32602 => new InvalidParamsException($message),
            -32603 => new InternalErrorException($message),
            -32001, 404 => new TaskNotFoundException($message),
            -32002 => new TaskNotCancelableException($message),
            -32003 => new PushNotificationNotSupportedException,
            -32004 => new UnsupportedOperationException($message),
            -32005 => new ContentTypeNotSupportedException($message),
            -32006 => new InvalidAgentResponseException($message),
            -32007 => new ExtendedAgentCardNotConfiguredException,
            -32008 => new ExtensionSupportRequiredException($message),
            -32009 => new VersionNotSupportedException($message),
            default => new InternalErrorException($message),
        };
    }

    /**
     * @param  array<string, mixed>  $error
     */
    private function fromReason(array $error, string $message, int $code): A2AException
    {
        $details = $error['data'] ?? $error['details'] ?? [];
        $reason = null;

        if (is_array($details)) {
            foreach ($details as $detail) {
                if (is_array($detail) && is_string($detail['reason'] ?? null)) {
                    $reason = $detail['reason'];
                    break;
                }
            }
        }

        return match ($reason) {
            'TASK_NOT_FOUND' => new TaskNotFoundException($message),
            'TASK_NOT_CANCELABLE' => new TaskNotCancelableException($message),
            'PUSH_NOTIFICATION_NOT_SUPPORTED' => new PushNotificationNotSupportedException,
            'UNSUPPORTED_OPERATION' => new UnsupportedOperationException($message),
            'CONTENT_TYPE_NOT_SUPPORTED' => new ContentTypeNotSupportedException($message),
            'INVALID_AGENT_RESPONSE' => new InvalidAgentResponseException($message),
            'EXTENDED_AGENT_CARD_NOT_CONFIGURED' => new ExtendedAgentCardNotConfiguredException,
            'EXTENSION_SUPPORT_REQUIRED' => new ExtensionSupportRequiredException($message),
            'VERSION_NOT_SUPPORTED' => new VersionNotSupportedException($message),
            default => $code === 404 ? new TaskNotFoundException($message) : new InvalidRequestException($message),
        };
    }

    protected function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}
