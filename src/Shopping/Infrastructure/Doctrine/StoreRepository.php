<?php

declare(strict_types=1);

namespace App\Shopping\Infrastructure\Doctrine;

use App\Shopping\Domain\Model\Store;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Store>
 */
final class StoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Store::class);
    }

    public function findActive(Uuid $householdId, Uuid $storeId): ?Store
    {
        return $this->findOneBy(['id' => $storeId, 'householdId' => $householdId, 'deletedAt' => null]);
    }

    /**
     * Alphabetisch, ohne Groß-/Kleinschreibung.
     *
     * @return list<Store>
     */
    public function findAllActive(Uuid $householdId): array
    {
        /** @var list<Store> $stores */
        $stores = $this->createQueryBuilder('s')
            ->where('s.householdId = :household')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('household', $householdId, UuidType::NAME)
            ->orderBy('LOWER(s.name)')
            ->getQuery()
            ->getResult();

        return $stores;
    }

    /**
     * seed_keys aller Läden aus der Startliste, auch gelöschter.
     *
     * @return list<string>
     */
    public function seedKeys(Uuid $householdId): array
    {
        /** @var list<string> $keys */
        $keys = $this->createQueryBuilder('s')
            ->select('s.seedKey')
            ->where('s.householdId = :household')
            ->andWhere('s.seedKey IS NOT NULL')
            ->setParameter('household', $householdId, UuidType::NAME)
            ->getQuery()
            ->getSingleColumnResult();

        return $keys;
    }

    /**
     * Von den angegebenen IDs nur die aktiven Läden dieses Haushalts, in der angegebenen Reihenfolge, ohne Dubletten.
     * Offline zugeordnete, inzwischen gelöschte Läden fallen so still weg.
     *
     * @param list<Uuid> $storeIds
     *
     * @return list<Uuid>
     */
    public function filterActiveIds(Uuid $householdId, array $storeIds): array
    {
        if ([] === $storeIds) {
            return [];
        }
        $active = [];
        foreach ($this->findAllActive($householdId) as $store) {
            $active[$store->getId()->toRfc4122()] = $store->getId();
        }
        $result = [];
        foreach ($storeIds as $id) {
            $key = $id->toRfc4122();
            if (isset($active[$key])) {
                $result[$key] = $active[$key];
            }
        }

        return array_values($result);
    }
}
