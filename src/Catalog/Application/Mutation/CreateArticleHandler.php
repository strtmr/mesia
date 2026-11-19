<?php

declare(strict_types=1);

namespace App\Catalog\Application\Mutation;

use App\Catalog\Domain\Model\Article;
use App\Catalog\Domain\TextNormalizer;
use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use App\Shared\Application\Sync\MutationContext;
use App\Shared\Application\Sync\MutationHandler;
use App\Shared\Application\Sync\MutationOutcome;
use App\Shared\Application\Sync\MutationRejected;
use Doctrine\ORM\EntityManagerInterface;

/**
 * "article.create": Das Gerät hat für einen unbekannten Freitext einen neuen Artikel angelegt.
 *
 * Gibt es den Begriff im Haushalt schon (Name oder Alias, z. B. weil das andere Gerät ihn offline gleichzeitig
 * angelegt hat), wird zusammengeführt: Status "merged" mit der ID des bestehenden Artikels.
 *
 * @implements MutationHandler<CreateArticlePayload>
 */
final readonly class CreateArticleHandler implements MutationHandler
{
    public function __construct(
        private ArticleRepository $articles,
        private CategoryRepository $categories,
        private TextNormalizer $normalizer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function mutationType(): string
    {
        return 'article.create';
    }

    public function payloadClass(): string
    {
        return CreateArticlePayload::class;
    }

    public function handle(object $payload, MutationContext $context): MutationOutcome
    {
        $existingById = $this->articles->find($payload->articleId);
        if (null !== $existingById) {
            if (!$existingById->getHouseholdId()->equals($context->householdId)) {
                throw new MutationRejected('Artikel-ID ist bereits vergeben.');
            }

            return MutationOutcome::applied();
        }

        $normalized = $this->normalizer->normalize($payload->name);
        if ('' === $normalized) {
            throw new MutationRejected('Der Artikelname enthält keine Buchstaben oder Ziffern.');
        }

        $existing = $this->articles->findByNormalizedTerm($context->householdId, $normalized);
        if (null !== $existing) {
            return MutationOutcome::merged($existing->getId());
        }

        if (null === $this->categories->findActive($context->householdId, $payload->categoryId)) {
            throw new MutationRejected('Kategorie nicht gefunden.');
        }

        $this->entityManager->persist(new Article($payload->articleId, $context->householdId, $payload->name, $normalized, $payload->categoryId, $context->now));

        return MutationOutcome::applied();
    }
}
