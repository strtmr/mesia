<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\ArticleSource;
use App\Catalog\Domain\Unit;
use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Shared\Domain\Syncable;
use App\Shared\Domain\SyncableFields;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Ein Artikel im Katalog eines Haushalts (z. B. "Milch"). Einkaufslisten-Einträge verweisen darauf.
 *
 * Die Kategorie wird als ID gespeichert. Ändert man auf der Liste die Kategorie eines Eintrags, übernimmt der Artikel
 * sie ("Lerneffekt").
 */
#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'article')]
#[ORM\Index(name: 'article_household_idx', columns: ['household_id'])]
#[ORM\UniqueConstraint(name: 'article_name_unique', columns: ['household_id', 'name_normalized'], options: ['where' => '(deleted_at IS NULL)'])]
class Article implements Syncable
{
    use SyncableFields;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 100)]
    private string $nameNormalized;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $categoryId;

    #[ORM\Column(length: 16, nullable: true, enumType: Unit::class)]
    private ?Unit $defaultUnit = null;

    #[ORM\Column(length: 16, enumType: ArticleSource::class, options: ['default' => 'user'])]
    private ArticleSource $source;

    /** Wie oft der Artikel abgehakt (gekauft) wurde. Sortiert „Erledigt“ nach Häufigkeit. */
    #[ORM\Column(options: ['default' => 0])]
    private int $purchaseCount = 0;

    /**
     * @param string $nameNormalized Ergebnis von {@see \App\Catalog\Domain\TextNormalizer::normalize()}
     */
    public function __construct(Uuid $id, Uuid $householdId, string $name, string $nameNormalized, Uuid $categoryId, \DateTimeImmutable $now, ArticleSource $source = ArticleSource::User)
    {
        $this->id = $id;
        $this->source = $source;
        $this->initSyncableFields($householdId, $now);
        $this->rename($name, $nameNormalized, $now);
        $this->categoryId = $categoryId;
    }

    public static function syncEntityType(): string
    {
        return 'article';
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNameNormalized(): string
    {
        return $this->nameNormalized;
    }

    public function isFromSeed(): bool
    {
        return ArticleSource::Seed === $this->source;
    }

    public function getCategoryId(): Uuid
    {
        return $this->categoryId;
    }

    public function rename(string $name, string $nameNormalized, \DateTimeImmutable $now): void
    {
        $name = trim($name);
        if ('' === $name || '' === $nameNormalized) {
            throw new \InvalidArgumentException('Der Artikelname darf nicht leer sein.');
        }

        $this->name = $name;
        $this->nameNormalized = $nameNormalized;
        $this->touch($now);
    }

    public function moveToCategory(Uuid $categoryId, \DateTimeImmutable $now): void
    {
        if (!$this->categoryId->equals($categoryId)) {
            $this->categoryId = $categoryId;
            $this->touch($now);
        }
    }

    public function recordPurchase(\DateTimeImmutable $now): void
    {
        ++$this->purchaseCount;
        $this->touch($now);
    }

    /** Abhaken zurückgenommen ("Wieder auf die Liste" direkt nach dem Abhaken) */
    public function revokePurchase(\DateTimeImmutable $now): void
    {
        if ($this->purchaseCount > 0) {
            --$this->purchaseCount;
            $this->touch($now);
        }
    }

    public function getPurchaseCount(): int
    {
        return $this->purchaseCount;
    }

    public function changeDefaultUnit(?Unit $unit, \DateTimeImmutable $now): void
    {
        $this->defaultUnit = $unit;
        $this->touch($now);
    }

    public function toSyncSnapshot(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'name' => $this->name,
            'name_normalized' => $this->nameNormalized,
            'category_id' => $this->categoryId->toRfc4122(),
            'default_unit' => $this->defaultUnit?->value,
            'purchase_count' => $this->purchaseCount,
        ] + $this->syncMeta();
    }
}
