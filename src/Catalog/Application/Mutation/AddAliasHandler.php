<?php

declare(strict_types=1);

namespace App\Catalog\Application\Mutation;

use App\Catalog\Domain\AliasSource;
use App\Catalog\Domain\Model\ArticleAlias;
use App\Catalog\Domain\TextNormalizer;
use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Shared\Application\Sync\MutationContext;
use App\Shared\Application\Sync\MutationHandler;
use App\Shared\Application\Sync\MutationOutcome;
use App\Shared\Application\Sync\MutationRejected;
use Doctrine\ORM\EntityManagerInterface;

/**
 * "alias.add": Der Benutzer hat einen Freitext einem bestehenden Artikel zugeordnet. Der Begriff wird künftig erkannt.
 *
 * @implements MutationHandler<AddAliasPayload>
 */
final readonly class AddAliasHandler implements MutationHandler
{
    public function __construct(
        private ArticleRepository $articles,
        private TextNormalizer $normalizer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function mutationType(): string
    {
        return 'alias.add';
    }

    public function payloadClass(): string
    {
        return AddAliasPayload::class;
    }

    public function handle(object $payload, MutationContext $context): MutationOutcome
    {
        $article = $this->articles->findActive($context->householdId, $payload->articleId)
            ?? throw new MutationRejected('Artikel nicht gefunden.');

        $normalized = $this->normalizer->normalize($payload->alias);
        if ('' === $normalized) {
            throw new MutationRejected('Der Begriff enthält keine Buchstaben oder Ziffern.');
        }

        $known = $this->articles->findByNormalizedTerm($context->householdId, $normalized);
        if (null !== $known) {
            if ($known->getId()->equals($article->getId())) {
                return MutationOutcome::ignored(); // Begriff ist für diesen Artikel schon bekannt
            }

            throw new MutationRejected(\sprintf('"%s" gehört bereits zum Artikel "%s".', $payload->alias, $known->getName()));
        }

        $this->entityManager->persist(new ArticleAlias($payload->aliasId, $context->householdId, $article->getId(), $normalized, AliasSource::User, $context->now));

        return MutationOutcome::applied();
    }
}
