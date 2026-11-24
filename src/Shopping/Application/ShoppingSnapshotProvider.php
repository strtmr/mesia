<?php

declare(strict_types=1);

namespace App\Shopping\Application;

use App\Shared\Application\Sync\SnapshotProvider;
use App\Shopping\Infrastructure\Doctrine\ShoppingListItemRepository;
use App\Shopping\Infrastructure\Doctrine\ShoppingListRepository;
use App\Shopping\Infrastructure\Doctrine\StoreRepository;
use Symfony\Component\Uid\Uuid;

final readonly class ShoppingSnapshotProvider implements SnapshotProvider
{
    public function __construct(
        private ShoppingListRepository $lists,
        private ShoppingListItemRepository $items,
        private StoreRepository $stores,
    ) {
    }

    public function syncablesFor(Uuid $householdId): iterable
    {
        yield from $this->stores->findAllActive($householdId);
        yield from $this->lists->findAllActive($householdId);
        yield from $this->items->findAllActive($householdId);
    }
}
