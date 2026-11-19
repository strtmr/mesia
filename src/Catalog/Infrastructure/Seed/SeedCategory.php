<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Seed;

final readonly class SeedCategory
{
    /**
     * @param list<SeedArticle> $articles
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $icon,
        public bool $fallback,
        public array $articles,
    ) {
    }
}
