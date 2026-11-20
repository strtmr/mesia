<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\AliasSource;
use App\Catalog\Domain\ArticleSource;
use App\Catalog\Domain\Model\Article;
use App\Catalog\Domain\Model\ArticleAlias;
use App\Catalog\Domain\TextNormalizer;
use App\Catalog\Infrastructure\Doctrine\ArticleAliasRepository;
use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use App\Catalog\Infrastructure\Seed\SeedArticle;
use App\Catalog\Infrastructure\Seed\SeedCatalogReader;
use App\Shared\Infrastructure\Persistence\HouseholdWriteLock;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Katalog-Abgleich: übernimmt Erweiterungen des Startkatalogs in einen bestehenden Haushalt.
 *
 * Regeln (bewusst vorsichtig, nichts wird gelöscht oder verschoben):
 * - Seed-Artikel, dessen Name und Aliase im Haushalt unbekannt sind → neu anlegen (Kategorie laut Seed).
 * - Fehlende Aliase → ergänzen, sofern der Begriff im Haushalt noch frei ist.
 * - Umbenennen nur, wenn der Artikel aus dem Startkatalog stammt und noch so heißt wie ein Alias des neuen Seed-Namens
 *   ("Zwiebel" → "Zwiebeln"). Selbst angelegte oder umbenannte Artikel behalten ihren Namen.
 * - Selbst angelegte Artikel werden nie Ziel des Abgleichs, außer sie heißen genau wie der Seed-Artikel.
 * - Kategorien der Artikel bleiben, wie der Haushalt sie hat (gelernte Zuordnung).
 *
 * Die Änderungen landen über die normalen Entities im Änderungsprotokoll und erreichen so alle Geräte.
 */
final readonly class CatalogUpdater
{
    public function __construct(
        private SeedCatalogReader $reader,
        private TextNormalizer $normalizer,
        private CategoryRepository $categories,
        private ArticleRepository $articles,
        private ArticleAliasRepository $aliases,
        private EntityManagerInterface $entityManager,
        private HouseholdWriteLock $writeLock,
        private ClockInterface $clock,
    ) {
    }

    public function update(Uuid $householdId, bool $dryRun = false): CatalogUpdateReport
    {
        if ($dryRun) {
            return $this->plan($householdId)->report();
        }

        return $this->entityManager->wrapInTransaction(function () use ($householdId): CatalogUpdateReport {
            $this->writeLock->acquire($householdId);
            $plan = $this->plan($householdId);
            $this->apply($householdId, $plan);

            return $plan->report();
        });
    }

    private function plan(Uuid $householdId): CatalogUpdatePlan
    {
        $plan = new CatalogUpdatePlan();

        $categoryBySeedKey = [];
        $fallbackCategoryId = null;
        foreach ($this->categories->findAllActive($householdId) as $category) {
            if (null !== $category->getSeedKey()) {
                $categoryBySeedKey[$category->getSeedKey()] = $category->getId();
            }
            if ($category->isFallback()) {
                $fallbackCategoryId = $category->getId();
            }
        }
        if (null === $fallbackCategoryId) {
            return $plan; // Haushalt ohne Katalog: nichts abzugleichen
        }

        // Wem gehört welcher Begriff (Name oder Alias)? Schlüssel: Normalform, Wert: Artikel-ID
        $owner = [];
        /** @var array<string, array{seed: bool, name: string, article: ?Article}> $info */
        $info = [];
        /** @var array<string, ArticleAlias> $aliasEntities */
        $aliasEntities = [];
        foreach ($this->articles->findAllActive($householdId) as $article) {
            $id = $article->getId()->toRfc4122();
            $owner[$article->getNameNormalized()] = $id;
            $info[$id] = ['seed' => $article->isFromSeed(), 'name' => $article->getNameNormalized(), 'article' => $article];
        }
        foreach ($this->aliases->findAllActive($householdId) as $alias) {
            $articleId = $alias->getArticleId()->toRfc4122();
            if (isset($info[$articleId])) {
                $owner[$alias->getAliasNormalized()] = $articleId;
                $aliasEntities[$alias->getAliasNormalized()] = $alias;
            }
        }

        foreach ($this->reader->read() as $seedCategory) {
            foreach ($seedCategory->articles as $seedArticle) {
                $this->planArticle($seedArticle, $categoryBySeedKey[$seedCategory->key] ?? $fallbackCategoryId, $plan, $owner, $info, $aliasEntities);
            }
        }

        return $plan;
    }

    /**
     * @param array<string, string>                                             $owner
     * @param array<string, array{seed: bool, name: string, article: ?Article}> $info
     * @param array<string, ArticleAlias>                                       $aliasEntities
     */
    private function planArticle(SeedArticle $seedArticle, Uuid $categoryId, CatalogUpdatePlan $plan, array &$owner, array &$info, array $aliasEntities): void
    {
        $nameNormalized = $this->normalizer->normalize($seedArticle->name);
        $aliasesNormalized = [];
        foreach ($seedArticle->aliases as $alias) {
            $normalized = $this->normalizer->normalize($alias);
            if ('' !== $normalized && $normalized !== $nameNormalized) {
                $aliasesNormalized[$normalized] = true;
            }
        }
        // array_keys macht aus rein numerischen Begriffen ("4711") Integer, deshalb zurück zu Strings
        $aliasesNormalized = array_map(strval(...), array_keys($aliasesNormalized));

        $target = $owner[$nameNormalized] ?? null;
        $rename = false;
        if (null !== $target) {
            // Seed-Name ist bekannt (als Name oder Alias). Heißt der Artikel noch wie ein Alias, wird umbenannt.
            $rename = $info[$target]['seed'] && $info[$target]['name'] !== $nameNormalized
                && \in_array($info[$target]['name'], $aliasesNormalized, true);
        } else {
            // Seed-Name unbekannt: Gehört einer der Aliase einem Artikel aus dem Startkatalog, ist es derselbe Artikel.
            foreach ($aliasesNormalized as $aliasNormalized) {
                $candidate = $owner[$aliasNormalized] ?? null;
                if (null !== $candidate && $info[$candidate]['seed']) {
                    $target = $candidate;
                    $rename = $info[$candidate]['name'] === $aliasNormalized;
                    break;
                }
            }
        }

        if (null === $target) {
            $id = Uuid::v7();
            $target = $id->toRfc4122();
            $plan->create($id, $seedArticle->name, $nameNormalized, $categoryId);
            $owner[$nameNormalized] = $target;
            $info[$target] = ['seed' => true, 'name' => $nameNormalized, 'article' => null];
        } elseif ($rename && null !== $info[$target]['article']) {
            $article = $info[$target]['article'];
            // Der neue Name war bisher Alias desselben Artikels ("Äpfel" bei "Apfel") → Alias entfällt
            if (isset($aliasEntities[$nameNormalized]) && ($owner[$nameNormalized] ?? null) === $target) {
                $plan->removeAlias($aliasEntities[$nameNormalized]);
            }
            $plan->rename($article, $seedArticle->name, $nameNormalized);
            unset($owner[$info[$target]['name']]);
            $owner[$nameNormalized] = $target;
            $info[$target]['name'] = $nameNormalized;
        }

        // Selbst umbenannter Seed-Artikel ("Strauchtomate"): Der neue Seed-Name führt als Alias dorthin
        foreach ([$nameNormalized, ...$aliasesNormalized] as $aliasNormalized) {
            if (!isset($owner[$aliasNormalized])) {
                $plan->addAlias(Uuid::fromString($target), $aliasNormalized);
                $owner[$aliasNormalized] = $target;
            }
        }
    }

    private function apply(Uuid $householdId, CatalogUpdatePlan $plan): void
    {
        $now = $this->clock->now();

        foreach ($plan->aliasRemovals as $alias) {
            $alias->softDelete($now);
        }
        foreach ($plan->renames as [$article, $name, $nameNormalized]) {
            $article->rename($name, $nameNormalized, $now);
        }
        foreach ($plan->creations as [$id, $name, $nameNormalized, $categoryId]) {
            $this->entityManager->persist(new Article($id, $householdId, $name, $nameNormalized, $categoryId, $now, ArticleSource::Seed));
        }
        foreach ($plan->aliasAdditions as [$articleId, $aliasNormalized]) {
            $this->entityManager->persist(new ArticleAlias(Uuid::v7(), $householdId, $articleId, $aliasNormalized, AliasSource::Seed, $now));
        }
    }
}
