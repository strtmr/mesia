<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use App\Shared\Application\Sync\MutationContext;
use App\Shared\Application\Sync\MutationHandler;
use App\Shared\Application\Sync\MutationOutcome;
use App\Shared\Application\Sync\MutationRejected;

/**
 * "item.move": umsortieren, auch in eine andere Kategorie.
 *
 * Lerneffekt: Wechselt die Kategorie, übernimmt der Artikel sie. Beim nächsten Mal landet er gleich dort.
 *
 * @implements MutationHandler<MoveItemPayload>
 */
final readonly class MoveItemHandler implements MutationHandler
{
    public function __construct(
        private ItemLookup $lookup,
        private CategoryRepository $categories,
        private ArticleRepository $articles,
    ) {
    }

    public static function mutationType(): string
    {
        return 'item.move';
    }

    public function payloadClass(): string
    {
        return MoveItemPayload::class;
    }

    public function handle(object $payload, MutationContext $context): MutationOutcome
    {
        $item = $this->lookup->get($context->householdId, $payload->itemId);
        $categoryId = $this->categories->activeOrFallback($context->householdId, $payload->categoryId)
            ?? throw new MutationRejected('Kategorie nicht gefunden.');

        $categoryChanged = $item->moveTo($categoryId, $payload->sortKey, $context->actorId, $context->now);

        $articleId = $item->getArticleId();
        if ($categoryChanged && null !== $articleId) {
            $this->articles->findActive($context->householdId, $articleId)?->moveToCategory($categoryId, $context->now);
        }

        return MutationOutcome::applied();
    }
}
