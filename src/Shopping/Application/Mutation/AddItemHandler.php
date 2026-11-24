<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use App\Shared\Application\Sync\MutationContext;
use App\Shared\Application\Sync\MutationHandler;
use App\Shared\Application\Sync\MutationOutcome;
use App\Shared\Application\Sync\MutationRejected;
use App\Shopping\Domain\ContributionSource;
use App\Shopping\Domain\Model\ShoppingListItem;
use App\Shopping\Domain\Quantity;
use App\Shopping\Infrastructure\Doctrine\ShoppingListItemRepository;
use App\Shopping\Infrastructure\Doctrine\ShoppingListRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * "item.add": Artikel auf die Liste setzen (manueller Beitrag).
 *
 * Steht der Artikel schon auf der Liste (offen oder erledigt), wird zusammengeführt: Der Beitrag geht an den
 * bestehenden Eintrag, ein erledigter Eintrag wird dabei reaktiviert. Antwort: "merged" mit der bestehenden ID.
 *
 * @implements MutationHandler<AddItemPayload>
 */
final readonly class AddItemHandler implements MutationHandler
{
    public function __construct(
        private ShoppingListRepository $lists,
        private ShoppingListItemRepository $items,
        private CategoryRepository $categories,
        private ArticleRepository $articles,
        private EntityManagerInterface $entityManager,
        private ItemExtras $extras,
    ) {
    }

    public static function mutationType(): string
    {
        return 'item.add';
    }

    public function payloadClass(): string
    {
        return AddItemPayload::class;
    }

    public function handle(object $payload, MutationContext $context): MutationOutcome
    {
        $existingById = $this->items->find($payload->itemId);
        if (null !== $existingById) {
            if (!$existingById->getHouseholdId()->equals($context->householdId)) {
                throw new MutationRejected('Eintrags-ID ist bereits vergeben.');
            }

            return MutationOutcome::applied();
        }

        if (null === $this->lists->findActive($context->householdId, $payload->listId)) {
            throw new MutationRejected('Liste nicht gefunden.');
        }
        // Offline in einer inzwischen gelöschten Kategorie angelegt → "Sonstiges" statt Ablehnung
        $categoryId = $this->categories->activeOrFallback($context->householdId, $payload->categoryId)
            ?? throw new MutationRejected('Kategorie nicht gefunden.');
        if (null !== $payload->articleId && null === $this->articles->findActive($context->householdId, $payload->articleId)) {
            throw new MutationRejected('Artikel nicht gefunden.');
        }

        $contribution = $payload->contribution;
        $quantity = Quantity::fromFloat($contribution->quantity);
        $storeIds = $this->extras->storeIds($context->householdId, $payload->storeIds);
        $validUntil = ItemExtras::date($payload->validUntil);

        if (null !== $payload->articleId) {
            $existing = $this->items->findActiveByArticle($payload->listId, $payload->articleId);
            if (null !== $existing) {
                // Erst der Beitrag (reaktiviert ggf. und entfernt dabei ein altes Datum), dann Läden und Datum
                $existing->addContribution($contribution->id, ContributionSource::Manual, null, $quantity, $contribution->unit, $context->actorId, $context->at, $context->now);
                ItemExtras::addTo($existing, $storeIds, $validUntil, $context->actorId, $context->now);

                return MutationOutcome::merged($existing->getId());
            }
        }

        $item = new ShoppingListItem(
            $payload->itemId,
            $context->householdId,
            $payload->listId,
            $payload->articleId,
            $payload->name,
            $categoryId,
            $payload->sortKey,
            $payload->note,
            $context->actorId,
            $context->at,
            $context->now,
        );
        $item->addContribution($contribution->id, ContributionSource::Manual, null, $quantity, $contribution->unit, $context->actorId, $context->at, $context->now);
        ItemExtras::addTo($item, $storeIds, $validUntil, $context->actorId, $context->now);
        $this->entityManager->persist($item);

        return MutationOutcome::applied();
    }
}
