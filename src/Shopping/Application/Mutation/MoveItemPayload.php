<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use App\Shared\Domain\SortKey;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class MoveItemPayload
{
    public function __construct(
        public Uuid $itemId,
        public Uuid $categoryId,
        #[Assert\Callback([self::class, 'validateSortKey'])]
        public string $sortKey,
    ) {
    }

    public static function validateSortKey(string $value, ExecutionContextInterface $context): void
    {
        if (!SortKey::isValid($value)) {
            $context->buildViolation('Ungültiger Sortierschlüssel.')->addViolation();
        }
    }
}
