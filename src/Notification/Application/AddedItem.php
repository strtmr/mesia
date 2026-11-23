<?php

declare(strict_types=1);

namespace App\Notification\Application;

final readonly class AddedItem
{
    public function __construct(
        public int $seq,
        public string $name,
        public ?string $actorName,
    ) {
    }
}
