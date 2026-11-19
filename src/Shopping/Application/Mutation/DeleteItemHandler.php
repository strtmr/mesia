<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use App\Shared\Application\Sync\MutationContext;
use App\Shared\Application\Sync\MutationHandler;
use App\Shared\Application\Sync\MutationOutcome;
use App\Shopping\Infrastructure\Doctrine\ShoppingListItemRepository;

/**
 * "item.delete": Eintrag entfernen (Soft Delete). Schon gelöscht → ignoriert.
 *
 * @implements MutationHandler<ItemIdPayload>
 */
final readonly class DeleteItemHandler implements MutationHandler
{
    public function __construct(private ShoppingListItemRepository $items)
    {
    }

    public static function mutationType(): string
    {
        return 'item.delete';
    }

    public function payloadClass(): string
    {
        return ItemIdPayload::class;
    }

    public function handle(object $payload, MutationContext $context): MutationOutcome
    {
        $item = $this->items->findActive($context->householdId, $payload->itemId);
        if (null === $item) {
            return MutationOutcome::ignored();
        }

        $item->delete($context->actorId, $context->now);

        return MutationOutcome::applied();
    }
}
