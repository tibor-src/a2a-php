<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Exceptions;

use RuntimeException;
use TiborSrc\A2A\Protocol;

abstract class A2AException extends RuntimeException
{
    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    public function __construct(
        string $message,
        public readonly int $jsonRpcCode,
        public readonly int $httpStatus,
        public readonly ?string $reason = null,
        public readonly array $details = [],
        public readonly string $domain = 'a2a-protocol.org',
        public readonly string $grpcStatus = '',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function errorInfoDetails(): array
    {
        if ($this->details !== []) {
            return $this->details;
        }

        if ($this->reason === null) {
            return [];
        }

        return [
            [
                '@type' => 'type.googleapis.com/google.rpc.ErrorInfo',
                'reason' => $this->reason,
                'domain' => $this->domain,
                'metadata' => [
                    'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
                    'protocolVersion' => Protocol::VERSION,
                ],
            ],
        ];
    }
}
