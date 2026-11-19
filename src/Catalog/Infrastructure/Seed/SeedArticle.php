<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Seed;

final readonly class SeedArticle
{
    /**
     * @param list<string> $aliases
     */
    public function __construct(
        public string $name,
        public array $aliases,
    ) {
    }
}
