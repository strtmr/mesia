<?php

declare(strict_types=1);

namespace App\Shared\Domain\ChangeLog;

enum ChangeOperation: string
{
    case Upsert = 'upsert';
    case Delete = 'delete';
}
