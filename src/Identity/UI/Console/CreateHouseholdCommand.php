<?php

declare(strict_types=1);

namespace App\Identity\UI\Console;

use App\Identity\Application\UserRegistration;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:household:create', description: 'Legt einen neuen Haushalt an')]
final readonly class CreateHouseholdCommand
{
    public function __construct(private UserRegistration $registration)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Name des Haushalts, z. B. "Familie Muster"')]
        string $name,
    ): int {
        $household = $this->registration->createHousehold($name);

        $io->success(\sprintf('Haushalt "%s" angelegt (ID %s).', $household->getName(), $household->getId()->toRfc4122()));

        return Command::SUCCESS;
    }
}
