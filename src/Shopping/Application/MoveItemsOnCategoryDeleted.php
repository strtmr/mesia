<?php

declare(strict_types=1);

namespace App\Shopping\Application;

use App\Catalog\Domain\Event\CategoryDeleted;
use App\Shopping\Infrastructure\Doctrine\ShoppingListItemRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Einträge einer gelöschten Kategorie wandern in die Fallback-Kategorie („Sonstiges“).
 */
#[AsEventListener]
final readonly class MoveItemsOnCategoryDeleted
{
    public function __construct(private ShoppingListItemRepository $items)
    {
    }

    public function __invoke(CategoryDeleted $event): void
    {
        foreach ($this->items->findByCategory($event->householdId, $event->categoryId) as $item) {
            $item->reassignCategory($event->fallbackCategoryId, $event->now);
        }
    }
}
