<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use App\Shared\Application\Sync\MutationRejected;
use App\Shopping\Domain\Model\ShoppingListItem;
use App\Shopping\Infrastructure\Doctrine\StoreRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Läden und „gültig bis“ aus Mutationen lesen und anwenden (item.add, item.update).
 */
final readonly class ItemExtras
{
    public const int MAX_STORES = 20;

    public function __construct(private StoreRepository $stores)
    {
    }

    /**
     * Nur aktive Läden des Haushalts. Unbekannte IDs fallen still weg (offline zugeordnet, inzwischen gelöscht).
     *
     * @return list<Uuid>
     */
    public function storeIds(Uuid $householdId, mixed $value): array
    {
        if (!\is_array($value) || \count($value) > self::MAX_STORES) {
            throw new MutationRejected(\sprintf('store_ids muss eine Liste mit höchstens %d Läden sein.', self::MAX_STORES));
        }
        $ids = [];
        foreach ($value as $id) {
            if (!\is_string($id) || !Uuid::isValid($id)) {
                throw new MutationRejected('Ungültige Laden-ID.');
            }
            $ids[] = Uuid::fromString($id);
        }

        return $this->stores->filterActiveIds($householdId, $ids);
    }

    /** "2026-11-28" → Datum, null → null */
    public static function date(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }
        $date = \is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new MutationRejected('Ungültiges Datum.');
        }

        return $date;
    }

    /**
     * @param list<Uuid> $storeIds
     */
    public static function addTo(ShoppingListItem $item, array $storeIds, ?\DateTimeImmutable $validUntil, Uuid $actorId, \DateTimeImmutable $now): void
    {
        if ([] !== $storeIds) {
            $item->addStores($storeIds, $actorId, $now);
        }
        if (null !== $validUntil) {
            $item->setValidUntil($validUntil, $actorId, $now);
        }
    }
}
