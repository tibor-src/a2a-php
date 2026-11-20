<?php

declare(strict_types=1);

namespace TiborSrc\A2A\Types;

enum Role: string
{
    case Unspecified = 'ROLE_UNSPECIFIED';
    case User = 'ROLE_USER';
    case Agent = 'ROLE_AGENT';
}
