<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Server;

interface AgentExecutor
{
    public function execute(RequestContext $context, EventQueue $queue): void;

    public function cancel(RequestContext $context, EventQueue $queue): void;
}
