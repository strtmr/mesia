<?php

declare(strict_types=1);

namespace App\Identity\UI\Console;

use App\Identity\Application\UserRegistration;
use App\Identity\Domain\Model\Household;
use App\Identity\Infrastructure\Doctrine\HouseholdRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Das Passwort wird interaktiv (verdeckt) abgefragt und nicht als Argument übergeben,
 * damit es nicht in der Shell-Historie landet.
 */
#[AsCommand(name: 'app:user:create', description: 'Legt einen Benutzer in einem Haushalt an')]
final readonly class CreateUserCommand
{
    public function __construct(
        private UserRegistration $registration,
        private HouseholdRepository $households,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'E-Mail-Adresse (Login)')]
        string $email,
        #[Argument(description: 'Anzeigename, z. B. "Marco"')]
        string $displayName,
        #[Option(description: 'ID des Haushalts. Kann entfallen, wenn es genau einen Haushalt gibt.')]
        ?string $household = null,
    ): int {
        $targetHousehold = $this->resolveHousehold($household);
        if (null === $targetHousehold) {
            $io->error('Haushalt nicht eindeutig. Bitte mit --household=<ID> angeben (oder zuerst app:household:create ausführen).');

            return Command::FAILURE;
        }

        $password = $io->askHidden(\sprintf('Passwort (mindestens %d Zeichen)', UserRegistration::MIN_PASSWORD_LENGTH));
        $confirmation = $io->askHidden('Passwort wiederholen');
        if (!\is_string($password) || $password !== $confirmation) {
            $io->error('Die Passwörter stimmen nicht überein.');

            return Command::FAILURE;
        }

        try {
            $user = $this->registration->createUser($targetHousehold, $email, $displayName, $password);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Benutzer %s (%s) im Haushalt "%s" angelegt.', $user->getDisplayName(), $user->getEmail(), $targetHousehold->getName()));

        return Command::SUCCESS;
    }

    private function resolveHousehold(?string $householdId): ?Household
    {
        if (null !== $householdId) {
            return Uuid::isValid($householdId) ? $this->households->find(Uuid::fromString($householdId)) : null;
        }

        $all = $this->households->findAllOrderedByName();

        return 1 === \count($all) ? $all[0] : null;
    }
}
