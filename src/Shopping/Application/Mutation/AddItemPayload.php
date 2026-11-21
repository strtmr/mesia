<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use App\Shared\Domain\SortKey;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class AddItemPayload
{
    public function __construct(
        public Uuid $itemId,
        public Uuid $listId,
        #[Assert\NotBlank]
        #[Assert\Length(max: 100, maxMessage: 'Der Name darf höchstens {{ limit }} Zeichen lang sein.')]
        public string $name,
        public Uuid $categoryId,
        #[Assert\Callback([self::class, 'validateSortKey'])]
        public string $sortKey,
        #[Assert\Valid]
        public ContributionPayload $contribution,
        public ?Uuid $articleId = null,
        #[Assert\Length(max: 200, maxMessage: 'Die Notiz darf höchstens {{ limit }} Zeichen lang sein.')]
        public ?string $note = null,
    ) {
    }

    public static function validateSortKey(string $value, ExecutionContextInterface $context): void
    {
        if (!SortKey::isValid($value)) {
            $context->buildViolation('Ungültiger Sortierschlüssel.')->addViolation();
        }
    }
}
