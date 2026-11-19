<?php

declare(strict_types=1);

namespace App\Shopping\Infrastructure\Doctrine;

use App\Shopping\Domain\Model\ShoppingList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ShoppingList>
 */
final class ShoppingListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShoppingList::class);
    }

    public function findActive(Uuid $householdId, Uuid $listId): ?ShoppingList
    {
        return $this->findOneBy(['id' => $listId, 'householdId' => $householdId, 'deletedAt' => null]);
    }

    /**
     * @return list<ShoppingList>
     */
    public function findAllActive(Uuid $householdId): array
    {
        return $this->findBy(['householdId' => $householdId, 'deletedAt' => null]);
    }
}
