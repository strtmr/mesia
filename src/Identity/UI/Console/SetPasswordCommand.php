<?php

declare(strict_types=1);

namespace App\Identity\UI\Console;

use App\Identity\Application\UserRegistration;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ersatz für "Passwort vergessen", solange es keinen Mailversand gibt.
 */
#[AsCommand(name: 'app:user:set-password', description: 'Setzt das Passwort eines Benutzers neu')]
final readonly class SetPasswordCommand
{
    public function __construct(private UserRegistration $registration)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'E-Mail-Adresse des Benutzers')]
        string $email,
    ): int {
        $password = $io->askHidden(\sprintf('Neues Passwort (mindestens %d Zeichen)', UserRegistration::MIN_PASSWORD_LENGTH));
        $confirmation = $io->askHidden('Passwort wiederholen');
        if (!\is_string($password) || $password !== $confirmation) {
            $io->error('Die Passwörter stimmen nicht überein.');

            return Command::FAILURE;
        }

        try {
            $this->registration->changePassword($email, $password);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('Passwort geändert. Bestehende "Angemeldet bleiben"-Cookies sind damit ungültig.');

        return Command::SUCCESS;
    }
}
