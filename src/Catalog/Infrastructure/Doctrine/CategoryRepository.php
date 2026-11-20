<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine;

use App\Catalog\Domain\Model\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Category>
 */
final class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findActive(Uuid $householdId, Uuid $categoryId): ?Category
    {
        return $this->findOneBy(['id' => $categoryId, 'householdId' => $householdId, 'deletedAt' => null]);
    }

    /**
     * @return list<Category>
     */
    public function findAllActive(Uuid $householdId): array
    {
        return $this->findBy(['householdId' => $householdId, 'deletedAt' => null]);
    }

    /**
     * Aktive Kategorien in Anzeigereihenfolge.
     *
     * @return list<Category>
     */
    public function findAllActiveOrdered(Uuid $householdId): array
    {
        return $this->findBy(['householdId' => $householdId, 'deletedAt' => null], ['sortKey' => 'ASC']);
    }

    public function findFallback(Uuid $householdId): ?Category
    {
        return $this->findOneBy(['householdId' => $householdId, 'isFallback' => true, 'deletedAt' => null]);
    }

    /**
     * Die Kategorie, falls sie (noch) existiert, sonst die Fallback-Kategorie. Für Mutationen von Geräten, die offline
     * eine inzwischen gelöschte Kategorie verwendet haben: Der Eintrag soll nicht verloren gehen.
     */
    public function activeOrFallback(Uuid $householdId, Uuid $categoryId): ?Uuid
    {
        return ($this->findActive($householdId, $categoryId) ?? $this->findFallback($householdId))?->getId();
    }

    /**
     * Alle Haushalte, die einen Katalog haben.
     *
     * @return list<Uuid>
     */
    public function findHouseholdIdsWithCatalog(): array
    {
        $ids = $this->createQueryBuilder('c')
            ->select('DISTINCT c.householdId')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_map(static fn (mixed $id): Uuid => $id instanceof Uuid ? $id : Uuid::fromString((string) $id), $ids));
    }

    public function hasAny(Uuid $householdId): bool
    {
        return null !== $this->findOneBy(['householdId' => $householdId]);
    }
}
