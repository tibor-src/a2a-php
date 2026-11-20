<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use TiborSrc\A2A\Exceptions\InvalidAgentResponseException;
use TiborSrc\A2A\Protocol;
use TiborSrc\A2A\Types\AgentCard;

final readonly class AgentCardResolver
{
    public function __construct(
        private ClientInterface $http,
        private RequestFactoryInterface $requests,
    ) {}

    public function resolve(string $baseUrl): AgentCard
    {
        $url = rtrim($baseUrl, '/').Protocol::AGENT_CARD_PATH;
        $response = $this->http->sendRequest($this->requests->createRequest('GET', $url));
        $decoded = json_decode((string) $response->getBody(), true);

        if (! is_array($decoded)) {
            throw new InvalidAgentResponseException('Agent Card is not a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return AgentCard::fromArray($decoded);
    }
}
