<?php

declare(strict_types=1);

namespace App\Recipe\Application;

use App\Recipe\Domain\Model\Dish;
use App\Recipe\Infrastructure\Doctrine\DishRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Gerichte anlegen, ändern und löschen. Immer im Haushalt des Aufrufers, fremde Gerichte gelten als nicht vorhanden.
 */
final readonly class DishEditor
{
    public function __construct(
        private DishRepository $dishes,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function get(Uuid $householdId, Uuid $dishId): Dish
    {
        return $this->dishes->findActive($householdId, $dishId) ?? throw new DishNotFound();
    }

    public function create(Uuid $householdId, Uuid $actorId, DishInput $input): Dish
    {
        $dish = new Dish($householdId, $input->name, $actorId, $this->clock->now());
        $this->apply($dish, $input);
        $this->entityManager->persist($dish);
        $this->entityManager->flush();

        return $dish;
    }

    public function update(Uuid $householdId, Uuid $dishId, DishInput $input): Dish
    {
        $dish = $this->get($householdId, $dishId);
        $dish->rename($input->name);
        $this->apply($dish, $input);
        $dish->touch($this->clock->now());
        $this->entityManager->flush();

        return $dish;
    }

    public function delete(Uuid $householdId, Uuid $dishId): void
    {
        $this->get($householdId, $dishId)->softDelete($this->clock->now());
        $this->entityManager->flush();
    }

    private function apply(Dish $dish, DishInput $input): void
    {
        $dish->describe($input->effort, $input->tags, $input->note, $input->sourceUrl);
        $recipe = $dish->getRecipe();
        $recipe->update($input->servings, $input->prepMinutes, $input->cookMinutes, $input->steps);
        $recipe->replaceIngredients($input->ingredients);
    }
}
