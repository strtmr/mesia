<?php

declare(strict_types=1);

namespace App\Identity\UI\Console;

use App\Identity\Application\UserRegistration;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Admin-Bereich (/admin) freischalten. Bewusst nur per Konsole, damit niemand sich über die App selbst befördert.
 */
#[AsCommand(name: 'app:user:admin', description: 'Gibt einem Benutzer Admin-Rechte (oder entzieht sie mit --revoke)')]
final readonly class GrantAdminCommand
{
    public function __construct(private UserRegistration $registration)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'E-Mail-Adresse des Benutzers')]
        string $email,
        #[Option(description: 'Admin-Rechte entziehen')]
        bool $revoke = false,
    ): int {
        try {
            $user = $this->registration->changeAdmin($email, !$revoke);
        } catch (\DomainException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf($revoke ? '%s ist kein Admin mehr.' : '%s ist jetzt Admin (Mehr → Admin).', $user->getEmail()));

        return Command::SUCCESS;
    }
}
