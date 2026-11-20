<?php

declare(strict_types=1);

namespace App\Catalog\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Neue Reihenfolge der Kategorien (vom Stimulus-Controller category-sort nach dem Ziehen gesendet).
 */
final readonly class ReorderCategoriesRequest
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\All([new Assert\Uuid()])]
        public array $ids,
        #[Assert\NotBlank]
        public string $token,
    ) {
    }
}
