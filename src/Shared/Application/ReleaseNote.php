<?php

declare(strict_types=1);

namespace App\Shared\Application;

final readonly class ReleaseNote
{
    /**
     * @param list<string> $changes
     */
    public function __construct(
        public string $version,
        public \DateTimeImmutable $date,
        public array $changes,
    ) {
    }
}
