<?php

declare(strict_types=1);

namespace App\Recipe\Domain\Model;

use App\Recipe\Domain\Effort;
use App\Recipe\Infrastructure\Doctrine\DishRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Ein Gericht, das der Haushalt kocht (oder bestellt): „Lasagne“, „Pizza bestellen“. Aggregatwurzel für seine Rezepte.
 *
 * Ein Gericht kann mehrere Rezepte (Varianten) haben, die Oberfläche zeigt vorerst nur das Standardrezept. Gerichte
 * werden nicht synchronisiert (Spezifikation Phase 2, Kapitel 2), gelöscht wird trotzdem weich: Beiträge auf der
 * Einkaufsliste verweisen später auf sie.
 */
#[ORM\Entity(repositoryClass: DishRepository::class)]
#[ORM\Table(name: 'dish')]
#[ORM\Index(name: 'dish_household_idx', columns: ['household_id'])]
class Dish
{
    public const int NAME_MAX_LENGTH = 100;
    public const int TAG_MAX_LENGTH = 30;
    public const int MAX_TAGS = 10;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $householdId;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    private string $name;

    #[ORM\Column(length: 16, nullable: true, enumType: Effort::class)]
    private ?Effort $effort = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $tags = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $sourceUrl = null;

    /** Zuletzt auf die Einkaufsliste gesetzt (ab Paket 2.3) */
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastListedAt = null;

    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $createdBy;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** @var Collection<int, Recipe> */
    #[ORM\OneToMany(targetEntity: Recipe::class, mappedBy: 'dish', cascade: ['persist'], orphanRemoval: true)]
    private Collection $recipes;

    public function __construct(Uuid $householdId, string $name, ?Uuid $createdBy, \DateTimeImmutable $now)
    {
        $this->id = Uuid::v7();
        $this->householdId = $householdId;
        $this->createdBy = $createdBy;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->recipes = new ArrayCollection([new Recipe($this, isDefault: true)]);
        $this->rename($name);
    }

    public function rename(string $name): void
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ('' === $name) {
            throw new \InvalidArgumentException('Ein Gericht braucht einen Namen.');
        }
        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('Der Name darf höchstens %d Zeichen lang sein.', self::NAME_MAX_LENGTH));
        }
        $this->name = $name;
    }

    /**
     * @param list<string> $tags
     */
    public function describe(?Effort $effort, array $tags, ?string $note, ?string $sourceUrl): void
    {
        $this->effort = $effort;
        $this->tags = self::normalizeTags($tags);
        $this->note = self::nullIfBlank($note);
        $this->sourceUrl = self::nullIfBlank($sourceUrl);
    }

    public function touch(\DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }

    public function softDelete(\DateTimeImmutable $now): void
    {
        $this->deletedAt ??= $now;
    }

    /**
     * Schlagworte: getrimmt, ohne Dubletten (unabhängig von Groß-/Kleinschreibung), höchstens MAX_TAGS, gekürzt.
     *
     * @param list<string> $tags
     *
     * @return list<string>
     */
    public static function normalizeTags(array $tags): array
    {
        $normalized = [];
        foreach ($tags as $tag) {
            $tag = mb_substr(trim(preg_replace('/\s+/u', ' ', $tag) ?? ''), 0, self::TAG_MAX_LENGTH);
            if ('' !== $tag && !isset($normalized[mb_strtolower($tag)])) {
                $normalized[mb_strtolower($tag)] = $tag;
            }
        }

        return \array_slice(array_values($normalized), 0, self::MAX_TAGS);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getHouseholdId(): Uuid
    {
        return $this->householdId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEffort(): ?Effort
    {
        return $this->effort;
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function getLastListedAt(): ?\DateTimeImmutable
    {
        return $this->lastListedAt;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    /** Das Rezept, das die Oberfläche zeigt (es gibt immer eins) */
    public function getRecipe(): Recipe
    {
        foreach ($this->recipes as $recipe) {
            if ($recipe->isDefault()) {
                return $recipe;
            }
        }

        throw new \LogicException(\sprintf('Gericht %s hat kein Standardrezept.', $this->id->toRfc4122()));
    }

    /** Hat das Gericht Zutaten oder eine Zubereitung, oder ist es nur ein Name? */
    public function hasRecipe(): bool
    {
        $recipe = $this->getRecipe();

        return [] !== $recipe->getIngredients() || null !== $recipe->getSteps();
    }

    private static function nullIfBlank(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
