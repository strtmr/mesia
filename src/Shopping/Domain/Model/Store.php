<?php

declare(strict_types=1);

namespace App\Shopping\Domain\Model;

use App\Shared\Domain\Syncable;
use App\Shared\Domain\SyncableFields;
use App\Shopping\Infrastructure\Doctrine\StoreRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Ein Laden (Supermarkt, Drogerie, Wochenmarkt), dem man Einträge der Liste zuordnen kann: „Bier nur bei Edeka“.
 * Synchronisiert, weil die Liste offline danach filtert. Verwaltet unter „Mehr → Läden“.
 */
#[ORM\Entity(repositoryClass: StoreRepository::class)]
#[ORM\Table(name: 'store')]
#[ORM\Index(name: 'store_household_idx', columns: ['household_id'])]
class Store implements Syncable
{
    use SyncableFields;

    public const int NAME_MAX_LENGTH = 40;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    private string $name;

    /** Aus der Startliste (sonst selbst angelegt) */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $seedKey;

    public function __construct(Uuid $id, Uuid $householdId, string $name, \DateTimeImmutable $now, ?string $seedKey = null)
    {
        $this->id = $id;
        $this->initSyncableFields($householdId, $now);
        $this->seedKey = $seedKey;
        $this->setName($name);
    }

    public static function syncEntityType(): string
    {
        return 'store';
    }

    public function rename(string $name, \DateTimeImmutable $now): void
    {
        $this->setName($name);
        $this->touch($now);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSeedKey(): ?string
    {
        return $this->seedKey;
    }

    public function toSyncSnapshot(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'name' => $this->name,
        ] + $this->syncMeta();
    }

    private function setName(string $name): void
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ('' === $name || mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('Der Name muss 1 bis %d Zeichen lang sein.', self::NAME_MAX_LENGTH));
        }
        $this->name = $name;
    }
}
