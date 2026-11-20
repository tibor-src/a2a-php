<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Client;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TiborSrc\A2A\Protocol;
use TiborSrc\A2A\Types\AgentCard;

final readonly class ClientFactory
{
    public function __construct(
        private ?ClientInterface $http = null,
        private ?RequestFactoryInterface $requests = null,
        private ?StreamFactoryInterface $streams = null,
    ) {}

    public static function create(?ClientInterface $http = null): self
    {
        return new self($http);
    }

    public function connect(string $agentUrl, ?Auth $auth = null, ?string $binding = null): Client
    {
        $card = new AgentCardResolver($this->http(), $this->requests())->resolve($agentUrl);

        return $this->forCard($card, $auth, $binding);
    }

    public function forCard(AgentCard $card, ?Auth $auth = null, ?string $binding = null): Client
    {
        $auth ??= Auth::none();
        $preferred = $binding ?? $card->supportedInterfaces[0]->protocolBinding;
        $interface = $card->preferredInterface($preferred) ?? $card->supportedInterfaces[0];

        return match ($interface->protocolBinding) {
            Protocol::HTTP_JSON_BINDING => new HttpJsonClient(
                $this->http(),
                $this->requests(),
                $this->streams(),
                $interface->url,
                $auth,
            ),
            default => new JsonRpcClient(
                $this->http(),
                $this->requests(),
                $this->streams(),
                $interface->url,
                $auth,
            ),
        };
    }

    private function http(): ClientInterface
    {
        return $this->http ?? Psr18ClientDiscovery::find();
    }

    private function requests(): RequestFactoryInterface
    {
        return $this->requests ?? Psr17FactoryDiscovery::findRequestFactory();
    }

    private function streams(): StreamFactoryInterface
    {
        return $this->streams ?? Psr17FactoryDiscovery::findStreamFactory();
    }
}
