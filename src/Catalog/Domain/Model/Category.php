<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use App\Shared\Domain\Syncable;
use App\Shared\Domain\SyncableFields;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'category')]
#[ORM\Index(name: 'category_household_idx', columns: ['household_id'])]
class Category implements Syncable
{
    use SyncableFields;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 60)]
    private string $name;

    #[ORM\Column(length: 16)]
    private string $icon;

    /** Fractional Index, Vergleich per Byte-Reihenfolge (Collation "C") */
    #[ORM\Column(length: 64, options: ['collation' => 'C'])]
    private string $sortKey;

    public function __construct(
        Uuid $id,
        Uuid $householdId,
        string $name,
        string $icon,
        string $sortKey,
        \DateTimeImmutable $now,
        /** Stabiler Schlüssel aus dem Startkatalog (z. B. "produce"), null bei eigenen Kategorien */
        #[ORM\Column(length: 40, nullable: true)]
        private ?string $seedKey = null,
        /** Genau eine Kategorie pro Haushalt nimmt unbekannte Artikel auf ("Sonstiges"). */
        #[ORM\Column]
        private bool $isFallback = false,
    ) {
        $this->id = $id;
        $this->initSyncableFields($householdId, $now);
        $this->rename($name, $icon, $now);
        $this->sortKey = $sortKey;
    }

    public static function syncEntityType(): string
    {
        return 'category';
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getSortKey(): string
    {
        return $this->sortKey;
    }

    public function getSeedKey(): ?string
    {
        return $this->seedKey;
    }

    public function isFallback(): bool
    {
        return $this->isFallback;
    }

    public function rename(string $name, string $icon, \DateTimeImmutable $now): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new \InvalidArgumentException('Der Kategoriename darf nicht leer sein.');
        }

        $this->name = $name;
        $this->icon = trim($icon);
        $this->touch($now);
    }

    public function moveTo(string $sortKey, \DateTimeImmutable $now): void
    {
        if ($sortKey !== $this->sortKey) {
            $this->sortKey = $sortKey;
            $this->touch($now);
        }
    }

    public function toSyncSnapshot(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'name' => $this->name,
            'icon' => $this->icon,
            'sort_key' => $this->sortKey,
            'seed_key' => $this->seedKey,
            'is_fallback' => $this->isFallback,
        ] + $this->syncMeta();
    }
}
