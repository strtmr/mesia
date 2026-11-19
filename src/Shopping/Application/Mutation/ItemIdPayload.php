<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use Symfony\Component\Uid\Uuid;

/**
 * Für Mutationen, die nur den Eintrag brauchen (item.check, item.uncheck, item.delete).
 */
final readonly class ItemIdPayload
{
    public function __construct(public Uuid $itemId)
    {
    }
}
