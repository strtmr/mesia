<?php

declare(strict_types=1);

namespace App\Shopping\Domain\Model;

use App\Shared\Domain\Syncable;
use App\Shared\Domain\SyncableFields;
use App\Shopping\Infrastructure\Doctrine\ShoppingListRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Eine Einkaufsliste. In Phase 1 hat jeder Haushalt genau eine.
 */
#[ORM\Entity(repositoryClass: ShoppingListRepository::class)]
#[ORM\Table(name: 'shopping_list')]
#[ORM\Index(name: 'shopping_list_household_idx', columns: ['household_id'])]
class ShoppingList implements Syncable
{
    use SyncableFields;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 60)]
    private string $name;

    public function __construct(Uuid $id, Uuid $householdId, string $name, \DateTimeImmutable $now)
    {
        $this->id = $id;
        $this->initSyncableFields($householdId, $now);
        $this->name = $name;
    }

    public static function syncEntityType(): string
    {
        return 'shopping_list';
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toSyncSnapshot(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'name' => $this->name,
        ] + $this->syncMeta();
    }
}
