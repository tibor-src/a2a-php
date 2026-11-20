<?php

declare(strict_types=1);

namespace TiborSrc\A2A;

final class Protocol
{
    public const string VERSION = '1.0';

    public const string SPEC_RELEASE = '1.0.1';

    public const string JSON_RPC_CONTENT_TYPE = 'application/json';

    public const string HTTP_JSON_CONTENT_TYPE = 'application/a2a+json';

    public const string SSE_CONTENT_TYPE = 'text/event-stream';

    public const string AGENT_CARD_PATH = '/.well-known/agent-card.json';

    public const string VERSION_HEADER = 'A2A-Version';

    public const string EXTENSIONS_HEADER = 'A2A-Extensions';

    public const string JSON_RPC_BINDING = 'JSONRPC';

    public const string HTTP_JSON_BINDING = 'HTTP+JSON';

    public const string GRPC_BINDING = 'GRPC';

    /**
     * @return list<string>
     */
    public static function supportedVersions(): array
    {
        return [self::VERSION];
    }

    public static function specification(): string
    {
        return self::SPEC_RELEASE;
    }

    /**
     * @return list<string>
     */
    public static function bindings(): array
    {
        return [
            self::JSON_RPC_BINDING,
            self::HTTP_JSON_BINDING,
            self::GRPC_BINDING,
        ];
    }
}
