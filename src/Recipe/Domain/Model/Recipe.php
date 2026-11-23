<?php

declare(strict_types=1);

namespace App\Recipe\Domain\Model;

use App\Recipe\Domain\RecipeSource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Ein Rezept (eine Variante) eines Gerichts: Portionen, Zutaten, Zubereitung. Gehört zum Aggregat {@see Dish}.
 */
#[ORM\Entity]
#[ORM\Table(name: 'recipe')]
class Recipe
{
    public const int MAX_SERVINGS = 99;
    public const int MAX_MINUTES = 1440;
    public const int MAX_INGREDIENTS = 100;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $servings = 2;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $prepMinutes = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $cookMinutes = null;

    /** Zubereitung, ein Schritt je Absatz */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $steps = null;

    #[ORM\Column(length: 16, enumType: RecipeSource::class)]
    private RecipeSource $sourceType = RecipeSource::Manual;

    /** @var Collection<int, IngredientLine> */
    #[ORM\OneToMany(targetEntity: IngredientLine::class, mappedBy: 'recipe', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $ingredients;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'recipes')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Dish $dish,
        #[ORM\Column]
        private bool $isDefault = false,
    ) {
        $this->id = Uuid::v7();
        $this->ingredients = new ArrayCollection();
    }

    public function update(int $servings, ?int $prepMinutes, ?int $cookMinutes, ?string $steps): void
    {
        if ($servings < 1 || $servings > self::MAX_SERVINGS) {
            throw new \InvalidArgumentException(\sprintf('Portionen: 1 bis %d.', self::MAX_SERVINGS));
        }
        foreach ([$prepMinutes, $cookMinutes] as $minutes) {
            if (null !== $minutes && ($minutes < 0 || $minutes > self::MAX_MINUTES)) {
                throw new \InvalidArgumentException(\sprintf('Zeiten: 0 bis %d Minuten.', self::MAX_MINUTES));
            }
        }

        $this->servings = $servings;
        $this->prepMinutes = $prepMinutes;
        $this->cookMinutes = $cookMinutes;
        $steps = null === $steps ? null : trim(str_replace("\r\n", "\n", $steps));
        $this->steps = '' === $steps ? null : $steps;
    }

    /**
     * Zutaten so übernehmen, wie sie eingegeben wurden (eine je Zeile). Unverändert gebliebene Zeilen behalten ihre
     * ID, damit spätere Korrekturen (Artikel-Zuordnung, Paket 2.2) erhalten bleiben.
     *
     * @param list<string> $lines
     */
    public function replaceIngredients(array $lines): void
    {
        $lines = array_values(array_filter(array_map(static fn (string $line): string => trim(preg_replace('/\s+/u', ' ', $line) ?? ''), $lines), static fn (string $line): bool => '' !== $line));
        if (\count($lines) > self::MAX_INGREDIENTS) {
            throw new \InvalidArgumentException(\sprintf('Höchstens %d Zutaten.', self::MAX_INGREDIENTS));
        }

        /** @var array<string, list<IngredientLine>> $existing */
        $existing = [];
        foreach ($this->ingredients as $line) {
            $existing[$line->getRawText()][] = $line;
        }

        $this->ingredients->clear();
        foreach ($lines as $position => $text) {
            $line = isset($existing[$text]) && [] !== $existing[$text] ? array_shift($existing[$text]) : new IngredientLine($this, $text);
            $line->moveTo($position);
            $this->ingredients->add($line);
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDish(): Dish
    {
        return $this->dish;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function getServings(): int
    {
        return $this->servings;
    }

    public function getPrepMinutes(): ?int
    {
        return $this->prepMinutes;
    }

    public function getCookMinutes(): ?int
    {
        return $this->cookMinutes;
    }

    public function getTotalMinutes(): ?int
    {
        return null === $this->prepMinutes && null === $this->cookMinutes ? null : (int) $this->prepMinutes + (int) $this->cookMinutes;
    }

    public function getSteps(): ?string
    {
        return $this->steps;
    }

    /**
     * Zubereitung als Schritte: getrennt durch Leerzeilen. Ohne Leerzeile ist jede Zeile ein Schritt.
     *
     * @return list<string>
     */
    public function getStepList(): array
    {
        if (null === $this->steps) {
            return [];
        }
        $parts = str_contains($this->steps, "\n\n") ? preg_split('/\n\s*\n/', $this->steps) : explode("\n", $this->steps);

        return array_values(array_filter(array_map(trim(...), $parts ?: []), static fn (string $step): bool => '' !== $step));
    }

    public function getSourceType(): RecipeSource
    {
        return $this->sourceType;
    }

    /**
     * @return list<IngredientLine>
     */
    public function getIngredients(): array
    {
        return array_values($this->ingredients->toArray());
    }

    /** Für das Textfeld im Formular: eine Zutat je Zeile, so wie eingegeben */
    public function getIngredientText(): string
    {
        return implode("\n", array_map(static fn (IngredientLine $line): string => $line->getRawText(), $this->getIngredients()));
    }
}
