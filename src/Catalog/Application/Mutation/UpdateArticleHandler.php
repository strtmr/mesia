<?php

declare(strict_types=1);

namespace App\Catalog\Application\Mutation;

use App\Catalog\Domain\TextNormalizer;
use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use App\Shared\Application\Sync\MutationContext;
use App\Shared\Application\Sync\MutationHandler;
use App\Shared\Application\Sync\MutationOutcome;
use App\Shared\Application\Sync\MutationRejected;

/**
 * "article.update": Name und/oder Kategorie eines Artikels ändern.
 *
 * @implements MutationHandler<UpdateArticlePayload>
 */
final readonly class UpdateArticleHandler implements MutationHandler
{
    public function __construct(
        private ArticleRepository $articles,
        private CategoryRepository $categories,
        private TextNormalizer $normalizer,
    ) {
    }

    public static function mutationType(): string
    {
        return 'article.update';
    }

    public function payloadClass(): string
    {
        return UpdateArticlePayload::class;
    }

    public function handle(object $payload, MutationContext $context): MutationOutcome
    {
        $article = $this->articles->findActive($context->householdId, $payload->articleId)
            ?? throw new MutationRejected('Artikel nicht gefunden.');

        if (null !== $payload->name) {
            $normalized = $this->normalizer->normalize($payload->name);
            $other = $this->articles->findByNormalizedTerm($context->householdId, $normalized);
            if (null !== $other && !$other->getId()->equals($article->getId())) {
                throw new MutationRejected(\sprintf('Es gibt bereits einen Artikel "%s".', $other->getName()));
            }
            $article->rename($payload->name, $normalized, $context->now);
        }

        if (null !== $payload->categoryId) {
            $categoryId = $this->categories->activeOrFallback($context->householdId, $payload->categoryId)
                ?? throw new MutationRejected('Kategorie nicht gefunden.');
            $article->moveToCategory($categoryId, $context->now);
        }

        return MutationOutcome::applied();
    }
}
