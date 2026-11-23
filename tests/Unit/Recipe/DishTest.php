<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recipe;

use App\Recipe\Domain\Model\Dish;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DishTest extends TestCase
{
    public function testEveryDishHasADefaultRecipe(): void
    {
        $dish = $this->dish('  Pizza   bestellen ');

        self::assertSame('Pizza bestellen', $dish->getName());
        self::assertSame(2, $dish->getRecipe()->getServings());
        self::assertFalse($dish->hasRecipe(), 'Nur ein Name, noch kein Rezept');
    }

    public function testTagsAreTrimmedDeduplicatedAndLimited(): void
    {
        self::assertSame(['Pasta', 'vegetarisch'], Dish::normalizeTags([' Pasta ', 'pasta', '', 'vegetarisch', 'PASTA']));
        self::assertCount(Dish::MAX_TAGS, Dish::normalizeTags(array_map(static fn (int $i): string => 'Tag '.$i, range(1, 15))));
        self::assertSame(str_repeat('x', Dish::TAG_MAX_LENGTH), Dish::normalizeTags([str_repeat('x', 50)])[0]);
    }

    public function testStepsAreSplitByBlankLinesOrElseByLine(): void
    {
        $recipe = $this->dish()->getRecipe();

        $recipe->update(4, null, null, "Zwiebeln würfeln\nund anbraten.\n\n  Hackfleisch dazu.  \n\n\n");
        self::assertSame(["Zwiebeln würfeln\nund anbraten.", 'Hackfleisch dazu.'], $recipe->getStepList());

        $recipe->update(4, null, null, "Wasser kochen\r\nNudeln rein\r\n");
        self::assertSame(['Wasser kochen', 'Nudeln rein'], $recipe->getStepList());

        $recipe->update(4, null, null, "  \n ");
        self::assertNull($recipe->getSteps());
    }

    public function testUnchangedIngredientLinesKeepTheirIdentity(): void
    {
        $recipe = $this->dish()->getRecipe();
        $recipe->replaceIngredients(['500 g Hackfleisch', '1 Dose Tomaten', '', '  2   Zwiebeln ']);
        [$meat, $tomatoes, $onions] = $recipe->getIngredients();
        self::assertSame('2 Zwiebeln', $onions->getRawText());

        $recipe->replaceIngredients(['2 Zwiebeln', '500 g Hackfleisch', 'Salz']);

        $lines = $recipe->getIngredients();
        self::assertSame(['2 Zwiebeln', '500 g Hackfleisch', 'Salz'], array_map(static fn ($line) => $line->getRawText(), $lines));
        self::assertSame($onions, $lines[0]);
        self::assertSame($meat, $lines[1]);
        self::assertSame([0, 1, 2], array_map(static fn ($line) => $line->getPosition(), $lines));
        self::assertNotContains($tomatoes, $lines);
    }

    public function testRejectsInvalidValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->dish()->getRecipe()->update(0, null, null, null);
    }

    private function dish(string $name = 'Lasagne'): Dish
    {
        return new Dish(Uuid::v7(), $name, Uuid::v7(), new \DateTimeImmutable());
    }
}
