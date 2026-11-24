<?php

declare(strict_types=1);

namespace App\Shopping\Application;

use App\Catalog\Domain\Event\ArticleDeleted;
use App\Shopping\Infrastructure\Doctrine\ShoppingListItemRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Gelöschter Artikel: Seine Einträge in „Erledigt“ verschwinden mit (sonst stünde z. B. ein Tippfehler dort für
 * immer). Offene Einträge bleiben, damit niemandem etwas von der Einkaufsliste verschwindet.
 */
#[AsEventListener]
final readonly class RemoveDoneItemsOnArticleDeleted
{
    public function __construct(private ShoppingListItemRepository $items)
    {
    }

    public function __invoke(ArticleDeleted $event): void
    {
        foreach ($this->items->findByArticle($event->householdId, $event->articleId) as $item) {
            if ($item->isChecked()) {
                $item->softDelete($event->now);
            }
        }
    }
}
