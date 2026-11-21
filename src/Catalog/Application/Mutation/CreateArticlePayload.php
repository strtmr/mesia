<?php

declare(strict_types=1);

namespace App\Catalog\Application\Mutation;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateArticlePayload
{
    public function __construct(
        public Uuid $articleId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100, maxMessage: 'Der Name darf höchstens {{ limit }} Zeichen lang sein.')]
        public string $name,
        public Uuid $categoryId,
    ) {
    }
}
