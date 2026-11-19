<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use App\Shared\Application\Sync\MutationRejected;
use App\Shopping\Domain\Model\ShoppingListItem;
use App\Shopping\Infrastructure\Doctrine\ShoppingListItemRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Lädt einen Eintrag des eigenen Haushalts oder lehnt die Mutation ab.
 * Fremde Einträge werden wie nicht vorhandene behandelt (keine Auskunft über andere Haushalte).
 */
final readonly class ItemLookup
{
    public function __construct(private ShoppingListItemRepository $items)
    {
    }

    public function get(Uuid $householdId, Uuid $itemId): ShoppingListItem
    {
        return $this->items->findActive($householdId, $itemId)
            ?? throw new MutationRejected('Eintrag nicht gefunden (evtl. inzwischen gelöscht).');
    }
}
