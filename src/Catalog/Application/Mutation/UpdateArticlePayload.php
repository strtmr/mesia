<?php

declare(strict_types=1);

namespace App\Catalog\Application\Mutation;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Nicht gesetzte Felder (null) bleiben unverändert.
 */
final readonly class UpdateArticlePayload
{
    public function __construct(
        public Uuid $articleId,
        #[Assert\Length(min: 1, max: 100)]
        public ?string $name = null,
        public ?Uuid $categoryId = null,
    ) {
    }
}
