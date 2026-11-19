<?php

declare(strict_types=1);

namespace App\Shopping\Domain\Model;

use App\Catalog\Domain\Unit;
use App\Shopping\Domain\ContributionSource;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Beitrag einer Quelle zu einem Listeneintrag, z. B. "manuell 2 l" oder "Wochenplan 500 ml".
 *
 * Gehört zum Aggregat {@see ShoppingListItem} und wird nur über dessen Methoden verändert.
 */
#[ORM\Entity]
#[ORM\Table(name: 'item_contribution')]
class ItemContribution
{
    #[ORM\Column]
    private bool $active = true;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $id,
        #[ORM\ManyToOne(inversedBy: 'contributions')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ShoppingListItem $item,
        #[ORM\Column(length: 20, enumType: ContributionSource::class)]
        private ContributionSource $source,
        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        private ?Uuid $sourceRef,
        #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
        private ?string $quantity,
        #[ORM\Column(length: 16, nullable: true, enumType: Unit::class)]
        private ?Unit $unit,
        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        private ?Uuid $createdBy,
        #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getItem(): ShoppingListItem
    {
        return $this->item;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function getSource(): ContributionSource
    {
        return $this->source;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'source' => $this->source->value,
            'source_ref' => $this->sourceRef?->toRfc4122(),
            'quantity' => null === $this->quantity ? null : (float) $this->quantity,
            'unit' => $this->unit?->value,
            'created_at' => $this->createdAt->format(\DATE_ATOM),
        ];
    }
}
