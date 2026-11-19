<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Infrastructure\Doctrine\ArticleAliasRepository;
use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use App\Shared\Application\Sync\SnapshotProvider;
use Symfony\Component\Uid\Uuid;

final readonly class CatalogSnapshotProvider implements SnapshotProvider
{
    public function __construct(
        private CategoryRepository $categories,
        private ArticleRepository $articles,
        private ArticleAliasRepository $aliases,
    ) {
    }

    public function syncablesFor(Uuid $householdId): iterable
    {
        yield from $this->categories->findAllActive($householdId);
        yield from $this->articles->findAllActive($householdId);
        yield from $this->aliases->findAllActive($householdId);
    }
}
