<?php

declare(strict_types=1);

namespace App\Catalog\UI\Console;

use App\Catalog\Application\CatalogUpdater;
use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:catalog:update', description: 'Übernimmt Erweiterungen des Startkatalogs in bestehende Haushalte (Katalog-Abgleich)')]
final readonly class UpdateCatalogCommand
{
    public function __construct(
        private CatalogUpdater $updater,
        private CategoryRepository $categories,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Nur anzeigen, was sich ändern würde')]
        bool $dryRun = false,
        #[Option(description: 'Nur diesen Haushalt (ID). Ohne Angabe: alle.')]
        ?string $household = null,
    ): int {
        if (null !== $household && !Uuid::isValid($household)) {
            $io->error('Ungültige Haushalts-ID.');

            return Command::FAILURE;
        }
        $targets = null === $household ? $this->categories->findHouseholdIdsWithCatalog() : [Uuid::fromString($household)];

        if ($dryRun) {
            $io->note('Probelauf: Es wird nichts gespeichert.');
        }

        foreach ($targets as $householdId) {
            $report = $this->updater->update($householdId, $dryRun);
            $io->section(\sprintf('Haushalt %s', $householdId->toRfc4122()));

            if ($report->isEmpty()) {
                $io->writeln('Katalog ist aktuell.');
                continue;
            }

            $io->writeln(\sprintf('+ %d Artikel, %d umbenannt, + %d Aliase, − %d Aliase', \count($report->created), \count($report->renamed), $report->aliasesAdded, $report->aliasesRemoved));
            if ([] !== $report->renamed) {
                $io->writeln('Umbenannt: '.implode(', ', array_map(static fn (array $pair): string => $pair[0].' → '.$pair[1], $report->renamed)));
            }
            if ([] !== $report->created) {
                $io->writeln('Neu: '.implode(', ', $report->created));
            }
        }

        $io->success($dryRun ? 'Probelauf beendet.' : 'Katalog-Abgleich beendet.');

        return Command::SUCCESS;
    }
}
