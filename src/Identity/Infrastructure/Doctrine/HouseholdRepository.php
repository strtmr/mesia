<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine;

use App\Identity\Domain\Model\Household;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Household>
 */
final class HouseholdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Household::class);
    }

    /**
     * @return list<Household>
     */
    public function findAllOrderedByName(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }
}
