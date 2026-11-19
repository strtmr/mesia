<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine;

use App\Catalog\Domain\Model\ArticleAlias;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ArticleAlias>
 */
final class ArticleAliasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleAlias::class);
    }

    public function findActiveByNormalized(Uuid $householdId, string $aliasNormalized): ?ArticleAlias
    {
        return $this->findOneBy(['householdId' => $householdId, 'aliasNormalized' => $aliasNormalized, 'deletedAt' => null]);
    }

    /**
     * @return list<ArticleAlias>
     */
    public function findAllActive(Uuid $householdId): array
    {
        return $this->findBy(['householdId' => $householdId, 'deletedAt' => null]);
    }
}
