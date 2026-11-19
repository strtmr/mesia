<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReactivateItemPayload
{
    public function __construct(
        public Uuid $itemId,
        #[Assert\Valid]
        public ContributionPayload $contribution,
    ) {
    }
}
