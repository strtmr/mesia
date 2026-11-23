<?php

declare(strict_types=1);

namespace App\Admin\Application;

use Symfony\Component\Uid\Uuid;

final readonly class HouseholdOverview
{
    /**
     * @param list<string> $members Anzeigenamen
     */
    public function __construct(
        public Uuid $id,
        public string $name,
        public array $members,
        public int $openItems,
        public int $purchases,
        public ?\DateTimeImmutable $lastActivity,
    ) {
    }
}
