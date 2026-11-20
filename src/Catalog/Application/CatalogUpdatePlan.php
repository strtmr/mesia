<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Model\Article;
use App\Catalog\Domain\Model\ArticleAlias;
use Symfony\Component\Uid\Uuid;

/**
 * Geplante Änderungen des Katalog-Abgleichs. Erst planen, dann ausführen: So kann --dry-run dieselbe Logik nutzen.
 *
 * @internal
 */
final class CatalogUpdatePlan
{
    /** @var list<array{Uuid, string, string, Uuid}> ID, Name, Normalform, Kategorie */
    public private(set) array $creations = [];
    /** @var list<array{Article, string, string}> Artikel, neuer Name, Normalform */
    public private(set) array $renames = [];
    /** @var list<array{Uuid, string}> Artikel-ID, Alias (Normalform) */
    public private(set) array $aliasAdditions = [];
    /** @var list<ArticleAlias> */
    public private(set) array $aliasRemovals = [];

    public function create(Uuid $id, string $name, string $nameNormalized, Uuid $categoryId): void
    {
        $this->creations[] = [$id, $name, $nameNormalized, $categoryId];
    }

    public function rename(Article $article, string $name, string $nameNormalized): void
    {
        $this->renames[] = [$article, $name, $nameNormalized];
    }

    public function addAlias(Uuid $articleId, string $aliasNormalized): void
    {
        $this->aliasAdditions[] = [$articleId, $aliasNormalized];
    }

    public function removeAlias(ArticleAlias $alias): void
    {
        $this->aliasRemovals[] = $alias;
    }

    public function report(): CatalogUpdateReport
    {
        return new CatalogUpdateReport(
            created: array_map(static fn (array $creation): string => $creation[1], $this->creations),
            renamed: array_map(static fn (array $rename): array => [$rename[0]->getName(), $rename[1]], $this->renames),
            aliasesAdded: \count($this->aliasAdditions),
            aliasesRemoved: \count($this->aliasRemovals),
        );
    }
}
