<?php

declare(strict_types=1);

namespace App\Admin\Application;

/**
 * Ein Artikel über alle Haushalte: Bezeichnung, in wie vielen Haushalten, wie oft gekauft.
 * key: Normalform (fasst die Haushalte zusammen), zum Löschen.
 */
final readonly class ArticleUsage
{
    public function __construct(
        public string $name,
        public ?string $category,
        public int $households,
        public int $purchases,
        public ?string $key = null,
    ) {
    }
}
