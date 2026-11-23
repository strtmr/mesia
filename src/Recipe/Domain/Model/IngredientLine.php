<?php

declare(strict_types=1);

namespace App\Recipe\Domain\Model;

use App\Catalog\Domain\Unit;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Eine Zutatenzeile wie „500 g Hackfleisch“. raw_text bleibt immer erhalten. Die erkannten Felder (Menge, Einheit,
 * Artikel …) füllt ab Paket 2.2 der Zutaten-Parser und kann sie jederzeit neu berechnen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ingredient_line')]
class IngredientLine
{
    public const int RAW_TEXT_MAX_LENGTH = 200;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $position = 0;

    #[ORM\Column(length: self::RAW_TEXT_MAX_LENGTH)]
    private string $rawText;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    private ?string $quantity = null;

    /** Obergrenze bei Spannen wie „2-3 Zehen“ */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    private ?string $quantityMax = null;

    #[ORM\Column(length: 16, nullable: true, enumType: Unit::class)]
    private ?Unit $unit = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $articleId = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $optional = false;

    /** Überschrift wie „Für die Soße“, gilt bis zur nächsten */
    #[ORM\Column(name: 'group_name', length: 60, nullable: true)]
    private ?string $group = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'ingredients')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Recipe $recipe,
        string $rawText,
    ) {
        if (mb_strlen($rawText) > self::RAW_TEXT_MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('Eine Zutat darf höchstens %d Zeichen lang sein.', self::RAW_TEXT_MAX_LENGTH));
        }
        $this->id = Uuid::v7();
        $this->rawText = $rawText;
        $this->name = mb_substr($rawText, 0, 100);
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getRawText(): string
    {
        return $this->rawText;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function getQuantityMax(): ?string
    {
        return $this->quantityMax;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function getArticleId(): ?Uuid
    {
        return $this->articleId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }
}
