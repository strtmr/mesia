<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\AliasSource;
use App\Catalog\Domain\ArticleSource;
use App\Catalog\Domain\Model\Article;
use App\Catalog\Domain\Model\ArticleAlias;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\TextNormalizer;
use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use App\Catalog\Infrastructure\Seed\SeedCatalogReader;
use App\Shared\Domain\SortKey;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Kopiert den Startkatalog in einen Haushalt. Danach gehört der Katalog dem Haushalt und ist frei änderbar.
 */
final readonly class CatalogSeeder
{
    public function __construct(
        private SeedCatalogReader $reader,
        private TextNormalizer $normalizer,
        private CategoryRepository $categories,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Idempotent: Hat der Haushalt schon Kategorien, passiert nichts.
     *
     * @return bool true, wenn der Katalog angelegt wurde
     */
    public function seed(Uuid $householdId): bool
    {
        if ($this->categories->hasAny($householdId)) {
            return false;
        }

        $now = $this->clock->now();
        $seedCategories = $this->reader->read();
        $sortKeys = SortKey::sequence(\count($seedCategories));
        $usedTerms = [];

        foreach ($seedCategories as $index => $seedCategory) {
            $category = new Category(Uuid::v7(), $householdId, $seedCategory->name, $seedCategory->icon, $sortKeys[$index], $now, $seedCategory->key, $seedCategory->fallback);
            $this->entityManager->persist($category);

            foreach ($seedCategory->articles as $seedArticle) {
                $article = new Article(Uuid::v7(), $householdId, $seedArticle->name, $this->normalizer->normalize($seedArticle->name), $category->getId(), $now, ArticleSource::Seed);
                $this->entityManager->persist($article);
                $usedTerms[$article->getNameNormalized()] = true;

                foreach ($seedArticle->aliases as $alias) {
                    $aliasNormalized = $this->normalizer->normalize($alias);
                    if (isset($usedTerms[$aliasNormalized])) {
                        continue; // sollte durch SeedCatalogTest ausgeschlossen sein, schützt aber die Unique-Constraints
                    }
                    $usedTerms[$aliasNormalized] = true;
                    $this->entityManager->persist(new ArticleAlias(Uuid::v7(), $householdId, $article->getId(), $aliasNormalized, AliasSource::Seed, $now));
                }
            }
        }

        $this->entityManager->flush();

        return true;
    }
}
