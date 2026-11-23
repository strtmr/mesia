<?php

declare(strict_types=1);

namespace App\Recipe\Application;

use App\Recipe\Domain\Effort;

/**
 * Was beim Anlegen oder Bearbeiten eines Gerichts eingegeben wurde (Gericht und sein Standardrezept zusammen).
 */
final readonly class DishInput
{
    /**
     * @param list<string> $tags
     * @param list<string> $ingredients eine Zutat je Eintrag, so wie eingegeben
     */
    public function __construct(
        public string $name,
        public int $servings = 2,
        public ?int $prepMinutes = null,
        public ?int $cookMinutes = null,
        public ?Effort $effort = null,
        public array $tags = [],
        public array $ingredients = [],
        public ?string $steps = null,
        public ?string $note = null,
        public ?string $sourceUrl = null,
    ) {
    }
}
