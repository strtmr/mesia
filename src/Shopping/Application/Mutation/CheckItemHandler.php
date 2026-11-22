<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Shared\Application\Sync\MutationContext;
use App\Shared\Application\Sync\MutationHandler;
use App\Shared\Application\Sync\MutationOutcome;
use App\Shopping\Domain\Model\PurchaseEvent;
use Doctrine\ORM\EntityManagerInterface;

/**
 * "item.check": abhaken. Erzeugt einen Eintrag in der Kaufhistorie.
 *
 * Ignoriert, wenn der Eintrag schon erledigt ist oder das Abhaken älter ist als ein späteres Reaktivieren.
 *
 * @implements MutationHandler<ItemIdPayload>
 */
final readonly class CheckItemHandler implements MutationHandler
{
    public function __construct(
        private ItemLookup $lookup,
        private EntityManagerInterface $entityManager,
        private ArticleRepository $articles,
    ) {
    }

    public static function mutationType(): string
    {
        return 'item.check';
    }

    public function payloadClass(): string
    {
        return ItemIdPayload::class;
    }

    public function handle(object $payload, MutationContext $context): MutationOutcome
    {
        $item = $this->lookup->get($context->householdId, $payload->itemId);

        if (!$item->check($context->actorId, $context->at, $context->now)) {
            return MutationOutcome::ignored();
        }

        $this->entityManager->persist(PurchaseEvent::fromCheckedItem($item, $context->actorId, $context->at));
        if (null !== $item->getArticleId()) {
            $this->articles->findActive($context->householdId, $item->getArticleId())?->recordPurchase($context->now);
        }

        return MutationOutcome::applied();
    }
}
