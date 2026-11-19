<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine;

use App\Catalog\Domain\Model\Article;
use App\Catalog\Domain\Model\ArticleAlias;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Article>
 */
final class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findActive(Uuid $householdId, Uuid $articleId): ?Article
    {
        return $this->findOneBy(['id' => $articleId, 'householdId' => $householdId, 'deletedAt' => null]);
    }

    /**
     * @return list<Article>
     */
    public function findAllActive(Uuid $householdId): array
    {
        return $this->findBy(['householdId' => $householdId, 'deletedAt' => null]);
    }

    /**
     * Sucht einen Artikel, dessen Name oder Alias der Normalform entspricht.
     */
    public function findByNormalizedTerm(Uuid $householdId, string $normalized): ?Article
    {
        $byName = $this->findOneBy(['householdId' => $householdId, 'nameNormalized' => $normalized, 'deletedAt' => null]);
        if (null !== $byName) {
            return $byName;
        }

        $alias = $this->getEntityManager()->getRepository(ArticleAlias::class)
            ->findOneBy(['householdId' => $householdId, 'aliasNormalized' => $normalized, 'deletedAt' => null]);

        return null === $alias ? null : $this->findActive($householdId, $alias->getArticleId());
    }

    /**
     * Verschiebt alle Artikel einer gelöschten Kategorie in eine andere.
     */
    public function reassignCategory(Uuid $householdId, Uuid $from, Uuid $to, \DateTimeImmutable $now): void
    {
        foreach ($this->findBy(['householdId' => $householdId, 'categoryId' => $from, 'deletedAt' => null]) as $article) {
            $article->moveToCategory($to, $now);
        }
    }
}
