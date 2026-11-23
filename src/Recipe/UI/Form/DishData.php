<?php

declare(strict_types=1);

namespace App\Recipe\UI\Form;

use App\Recipe\Application\DishInput;
use App\Recipe\Domain\Effort;
use App\Recipe\Domain\Model\Dish;
use App\Recipe\Domain\Model\IngredientLine;
use App\Recipe\Domain\Model\Recipe;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Formulardaten „Gericht anlegen/bearbeiten“: Gericht und Standardrezept in einem Formular.
 */
final class DishData
{
    #[Assert\NotBlank(message: 'Bitte einen Namen eingeben.')]
    #[Assert\Length(max: Dish::NAME_MAX_LENGTH, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $name = null;

    #[Assert\NotNull(message: 'Bitte die Portionen angeben.')]
    #[Assert\Range(notInRangeMessage: 'Zwischen {{ min }} und {{ max }} Portionen.', min: 1, max: Recipe::MAX_SERVINGS)]
    public ?int $servings = 2;

    #[Assert\Range(notInRangeMessage: 'Zwischen {{ min }} und {{ max }} Minuten.', min: 0, max: Recipe::MAX_MINUTES)]
    public ?int $prepMinutes = null;

    #[Assert\Range(notInRangeMessage: 'Zwischen {{ min }} und {{ max }} Minuten.', min: 0, max: Recipe::MAX_MINUTES)]
    public ?int $cookMinutes = null;

    public ?Effort $effort = null;

    /** Kommagetrennt: "Pasta, vegetarisch" */
    #[Assert\Length(max: 400, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $tags = null;

    /** Eine Zutat je Zeile */
    #[Assert\Length(max: 20000, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $ingredients = null;

    #[Assert\Length(max: 20000, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $steps = null;

    #[Assert\Length(max: 2000, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $note = null;

    #[Assert\Url(message: 'Das ist keine gültige Adresse.', requireTld: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $sourceUrl = null;

    public static function fromDish(Dish $dish): self
    {
        $recipe = $dish->getRecipe();
        $data = new self();
        $data->name = $dish->getName();
        $data->servings = $recipe->getServings();
        $data->prepMinutes = $recipe->getPrepMinutes();
        $data->cookMinutes = $recipe->getCookMinutes();
        $data->effort = $dish->getEffort();
        $data->tags = implode(', ', $dish->getTags());
        $data->ingredients = $recipe->getIngredientText();
        $data->steps = $recipe->getSteps();
        $data->note = $dish->getNote();
        $data->sourceUrl = $dish->getSourceUrl();

        return $data;
    }

    public function toInput(): DishInput
    {
        return new DishInput(
            name: (string) $this->name,
            servings: (int) $this->servings,
            prepMinutes: $this->prepMinutes,
            cookMinutes: $this->cookMinutes,
            effort: $this->effort,
            tags: self::splitTags($this->tags),
            ingredients: self::lines($this->ingredients),
            steps: $this->steps,
            note: $this->note,
            sourceUrl: $this->sourceUrl,
        );
    }

    #[Assert\Callback]
    public function validateIngredients(ExecutionContextInterface $context): void
    {
        $lines = self::lines($this->ingredients);
        if (\count($lines) > Recipe::MAX_INGREDIENTS) {
            $context->buildViolation(\sprintf('Höchstens %d Zutaten.', Recipe::MAX_INGREDIENTS))->atPath('ingredients')->addViolation();
        }
        foreach ($lines as $line) {
            if (mb_strlen($line) > IngredientLine::RAW_TEXT_MAX_LENGTH) {
                $context->buildViolation(\sprintf('Eine Zutat darf höchstens %d Zeichen lang sein: „%s …“', IngredientLine::RAW_TEXT_MAX_LENGTH, mb_substr($line, 0, 30)))
                    ->atPath('ingredients')->addViolation();
            }
        }
        if (\count(self::splitTags($this->tags)) > Dish::MAX_TAGS) {
            $context->buildViolation(\sprintf('Höchstens %d Schlagworte.', Dish::MAX_TAGS))->atPath('tags')->addViolation();
        }
    }

    /**
     * @return list<string>
     */
    private static function lines(?string $text): array
    {
        $lines = array_map(trim(...), preg_split('/\R/u', (string) $text) ?: []);

        return array_values(array_filter($lines, static fn (string $line): bool => '' !== $line));
    }

    /**
     * @return list<string>
     */
    private static function splitTags(?string $text): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', (string) $text)), static fn (string $tag): bool => '' !== $tag));
    }
}
