<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use App\Shared\Application\Sync\MutationContext;
use App\Shared\Application\Sync\MutationHandler;
use App\Shared\Application\Sync\MutationOutcome;
use App\Shopping\Infrastructure\Doctrine\PurchaseEventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * "item.uncheck": Abhaken rückgängig machen ("Rückgängig"-Toast). Entfernt den zugehörigen Kaufhistorien-Eintrag.
 *
 * @implements MutationHandler<ItemIdPayload>
 */
final readonly class UncheckItemHandler implements MutationHandler
{
    public function __construct(
        private ItemLookup $lookup,
        private PurchaseEventRepository $purchases,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function mutationType(): string
    {
        return 'item.uncheck';
    }

    public function payloadClass(): string
    {
        return ItemIdPayload::class;
    }

    public function handle(object $payload, MutationContext $context): MutationOutcome
    {
        $item = $this->lookup->get($context->householdId, $payload->itemId);

        if (!$item->uncheck($context->actorId, $context->now)) {
            return MutationOutcome::ignored();
        }

        $purchase = $this->purchases->findLatestForItem($item->getId());
        if (null !== $purchase) {
            $this->entityManager->remove($purchase);
        }

        return MutationOutcome::applied();
    }
}
