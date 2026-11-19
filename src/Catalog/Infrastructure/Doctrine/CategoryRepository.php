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

    public function hasAny(Uuid $householdId): bool
    {
        return null !== $this->findOneBy(['householdId' => $householdId]);
    }
}
