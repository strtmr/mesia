<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Shared\Domain\Event\HouseholdCreated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 10)]
final readonly class SeedCatalogOnHouseholdCreated
{
    public function __construct(private CatalogSeeder $seeder)
    {
    }

    public function __invoke(HouseholdCreated $event): void
    {
        $this->seeder->seed($event->householdId);
    }
}
