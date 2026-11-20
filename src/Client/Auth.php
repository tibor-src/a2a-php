<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Client;

use Psr\Http\Message\RequestInterface;

final readonly class Auth
{
    /**
     * @param  callable(RequestInterface): RequestInterface  $callback
     */
    public function __construct(
        private mixed $callback,
    ) {}

    public static function bearer(string $token): self
    {
        return new self(fn (RequestInterface $request): RequestInterface => $request->withHeader('Authorization', 'Bearer '.$token));
    }

    public static function none(): self
    {
        return new self(fn (RequestInterface $request): RequestInterface => $request);
    }

    public function apply(RequestInterface $request): RequestInterface
    {
        return ($this->callback)($request);
    }
}
