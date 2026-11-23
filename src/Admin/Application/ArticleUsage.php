<?php

declare(strict_types=1);

namespace App\Admin\Application;

/**
 * Ein Artikel über alle Haushalte: häufigste Schreibweise, in wie vielen Haushalten, wie oft gekauft.
 */
final readonly class ArticleUsage
{
    public function __construct(
        public string $name,
        public ?string $category,
        public int $households,
        public int $purchases,
    ) {
    }
}
