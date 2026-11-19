<?php

declare(strict_types=1);

namespace App\Shopping\Domain\Model;

use App\Catalog\Domain\Unit;
use App\Shopping\Infrastructure\Doctrine\PurchaseEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Kaufhistorie: Jedes Abhaken erzeugt einen Eintrag. Grundlage für Vorschläge und Lernen in späteren Phasen.
 * Wird nicht synchronisiert.
 */
#[ORM\Entity(repositoryClass: PurchaseEventRepository::class)]
#[ORM\Table(name: 'purchase_event')]
#[ORM\Index(name: 'purchase_event_household_idx', columns: ['household_id', 'purchased_at'])]
#[ORM\Index(name: 'purchase_event_item_idx', columns: ['item_id'])]
class PurchaseEvent
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $id,
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $householdId,
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $itemId,
        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        private ?Uuid $articleId,
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
        private ?string $quantity,
        #[ORM\Column(length: 16, nullable: true, enumType: Unit::class)]
        private ?Unit $unit,
        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        private ?Uuid $userId,
        #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
        private \DateTimeImmutable $purchasedAt,
    ) {
    }

    public static function fromCheckedItem(ShoppingListItem $item, Uuid $userId, \DateTimeImmutable $at): self
    {
        $quantity = $item->effectiveQuantity();

        return new self(Uuid::v7(), $item->getHouseholdId(), $item->getId(), $item->getArticleId(), $item->getName(), $quantity['quantity'] ?? null, $quantity['unit'] ?? null, $userId, $at);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getHouseholdId(): Uuid
    {
        return $this->householdId;
    }

    public function getItemId(): Uuid
    {
        return $this->itemId;
    }

    public function getArticleId(): ?Uuid
    {
        return $this->articleId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function getUserId(): ?Uuid
    {
        return $this->userId;
    }

    public function getPurchasedAt(): \DateTimeImmutable
    {
        return $this->purchasedAt;
    }
}
