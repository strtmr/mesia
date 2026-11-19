<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\AliasSource;
use App\Catalog\Infrastructure\Doctrine\ArticleAliasRepository;
use App\Shared\Domain\Syncable;
use App\Shared\Domain\SyncableFields;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Weiterer Begriff für einen Artikel, z. B. "Möhre" für "Karotte". Gespeichert wird nur die Normalform.
 */
#[ORM\Entity(repositoryClass: ArticleAliasRepository::class)]
#[ORM\Table(name: 'article_alias')]
#[ORM\Index(name: 'article_alias_household_idx', columns: ['household_id'])]
#[ORM\UniqueConstraint(name: 'article_alias_unique', columns: ['household_id', 'alias_normalized'], options: ['where' => '(deleted_at IS NULL)'])]
class ArticleAlias implements Syncable
{
    use SyncableFields;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    public function __construct(
        Uuid $id,
        Uuid $householdId,
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $articleId,
        #[ORM\Column(length: 100)]
        private string $aliasNormalized,
        #[ORM\Column(length: 16, enumType: AliasSource::class)]
        private AliasSource $source,
        \DateTimeImmutable $now,
    ) {
        if ('' === $aliasNormalized) {
            throw new \InvalidArgumentException('Ein Alias darf nicht leer sein.');
        }

        $this->id = $id;
        $this->initSyncableFields($householdId, $now);
    }

    public static function syncEntityType(): string
    {
        return 'article_alias';
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getArticleId(): Uuid
    {
        return $this->articleId;
    }

    public function getAliasNormalized(): string
    {
        return $this->aliasNormalized;
    }

    public function toSyncSnapshot(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'article_id' => $this->articleId->toRfc4122(),
            'alias_normalized' => $this->aliasNormalized,
            'source' => $this->source->value,
        ] + $this->syncMeta();
    }
}
