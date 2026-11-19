<?php

declare(strict_types=1);

namespace App\Shopping\Application;

use App\Shared\Domain\Event\HouseholdCreated;
use App\Shopping\Domain\Model\ShoppingList;
use App\Shopping\Infrastructure\Doctrine\ShoppingListRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;

/**
 * Jeder Haushalt bekommt eine Einkaufsliste. Idempotent.
 */
#[AsEventListener]
final readonly class CreateDefaultListOnHouseholdCreated
{
    public const string DEFAULT_NAME = 'Einkaufsliste';

    public function __construct(
        private ShoppingListRepository $lists,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(HouseholdCreated $event): void
    {
        if ([] !== $this->lists->findAllActive($event->householdId)) {
            return;
        }

        $this->entityManager->persist(new ShoppingList(Uuid::v7(), $event->householdId, self::DEFAULT_NAME, $this->clock->now()));
        $this->entityManager->flush();
    }
}
