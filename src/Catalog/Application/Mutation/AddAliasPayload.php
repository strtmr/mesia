<?php

declare(strict_types=1);

namespace App\Catalog\Application\Mutation;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddAliasPayload
{
    public function __construct(
        public Uuid $aliasId,
        public Uuid $articleId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $alias,
    ) {
    }
}
