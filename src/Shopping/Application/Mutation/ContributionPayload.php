<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use App\Catalog\Domain\Unit;
use App\Shopping\Domain\Quantity;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContributionPayload
{
    public function __construct(
        public Uuid $id,
        #[Assert\Positive(message: 'Die Menge muss größer als 0 sein.')]
        #[Assert\LessThanOrEqual(Quantity::MAX, message: 'Die Menge darf höchstens {{ compared_value }} sein.')]
        public ?float $quantity = null,
        public ?Unit $unit = null,
    ) {
    }
}
