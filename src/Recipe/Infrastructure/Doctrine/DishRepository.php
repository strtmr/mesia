<?php

declare(strict_types=1);

namespace App\Recipe\Infrastructure\Doctrine;

use App\Recipe\Domain\Model\Dish;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Dish>
 */
final class DishRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dish::class);
    }

    public function findActive(Uuid $householdId, Uuid $dishId): ?Dish
    {
        $dish = $this->find($dishId);

        return null !== $dish && !$dish->isDeleted() && $dish->getHouseholdId()->equals($householdId) ? $dish : null;
    }

    /**
     * Alle Gerichte des Haushalts, alphabetisch (ohne Groß-/Kleinschreibung).
     *
     * @return list<Dish>
     */
    public function findAllActive(Uuid $householdId): array
    {
        /** @var list<Dish> $dishes */
        $dishes = $this->createQueryBuilder('d')
            ->addSelect('r')
            ->leftJoin('d.recipes', 'r')
            ->where('d.householdId = :household')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('household', $householdId, UuidType::NAME)
            ->orderBy('LOWER(d.name)')
            ->getQuery()
            ->getResult();

        return $dishes;
    }
}
