<?php

declare(strict_types=1);

namespace App\Identity\UI\Console;

use App\Identity\Infrastructure\Doctrine\HouseholdRepository;
use App\Shared\Domain\Event\HouseholdCreated;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Ergänzt fehlende Grunddaten (Startkatalog, Einkaufsliste) in bestehenden Haushalten, z. B. nach einem Update.
 * Gefahrlos mehrfach ausführbar: Vorhandenes bleibt unverändert.
 */
#[AsCommand(name: 'app:household:initialize', description: 'Legt fehlende Grunddaten (Katalog, Liste) in Haushalten an')]
final readonly class InitializeHouseholdsCommand
{
    public function __construct(
        private HouseholdRepository $households,
        private EventDispatcherInterface $eventDispatcher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Nur diesen Haushalt (ID). Ohne Angabe: alle.')]
        ?string $household = null,
    ): int {
        $targets = null === $household
            ? $this->households->findAllOrderedByName()
            : array_filter([Uuid::isValid($household) ? $this->households->find(Uuid::fromString($household)) : null]);

        if ([] === $targets) {
            $io->warning('Kein Haushalt gefunden.');

            return Command::FAILURE;
        }

        foreach ($targets as $target) {
            $this->entityManager->wrapInTransaction(fn () => $this->eventDispatcher->dispatch(new HouseholdCreated($target->getId())));
            $io->writeln(\sprintf('✓ %s', $target->getName()));
        }

        $io->success('Fertig.');

        return Command::SUCCESS;
    }
}
