<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Model\Household;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Doctrine\UserRepository;
use App\Shared\Domain\Event\HouseholdCreated;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Legt Haushalte und Benutzer an und ändert sie: über Konsolenbefehle, den Admin-Bereich und „Mehr → Konto“.
 * Eine Selbstregistrierung gibt es noch nicht.
 */
final readonly class UserRegistration
{
    public const int MIN_PASSWORD_LENGTH = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
        private UserPasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Legt den Haushalt an und löst {@see HouseholdCreated} aus, alles in einer Transaktion. Andere Module legen
     * daraufhin ihre Grunddaten an (Startkatalog, Einkaufsliste).
     */
    public function createHousehold(string $name): Household
    {
        return $this->entityManager->wrapInTransaction(function () use ($name): Household {
            $household = new Household($name, $this->clock->now());
            $this->entityManager->persist($household);
            $this->entityManager->flush();

            $this->eventDispatcher->dispatch(new HouseholdCreated($household->getId()));

            return $household;
        });
    }

    /**
     * Neuer Haushalt mit seiner ersten Person, gemeinsam oder gar nicht (z. B. wenn die E-Mail-Adresse vergeben ist).
     */
    public function createHouseholdWithUser(string $householdName, string $email, string $displayName, string $plainPassword): User
    {
        return $this->entityManager->wrapInTransaction(
            fn (): User => $this->createUser($this->createHousehold($householdName), $email, $displayName, $plainPassword),
        );
    }

    public function createUser(Household $household, string $email, string $displayName, string $plainPassword): User
    {
        if (null !== $this->users->findOneByEmail($email)) {
            throw new \DomainException(\sprintf('Es gibt bereits einen Benutzer mit der E-Mail-Adresse "%s".', $email));
        }

        self::assertPasswordStrength($plainPassword);

        $user = new User($household, $email, $displayName, $this->clock->now());
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Ändert das Passwort. Da der Hash in die Remember-me-Signatur eingeht, werden dadurch alle
     * "Angemeldet bleiben"-Cookies dieses Benutzers ungültig, laufende Sitzungen enden beim nächsten Aufruf.
     */
    public function changePassword(string $email, string $plainPassword): void
    {
        $user = $this->users->findOneByEmail($email)
            ?? throw new \DomainException(\sprintf('Es gibt keinen Benutzer mit der E-Mail-Adresse "%s".', $email));

        $this->setPassword($user, $plainPassword);
    }

    /**
     * Wie {@see changePassword()}, für einen schon geladenen Benutzer (Admin-Bereich, „Mehr → Konto“).
     */
    public function setPassword(User $user, string $plainPassword): void
    {
        self::assertPasswordStrength($plainPassword);

        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();
    }

    /**
     * Anzeigename ändern. Die App liest Namen immer frisch aus app_user (/api/me, Mitteilungen), nichts ist kopiert.
     */
    public function renameUser(User $user, string $displayName): void
    {
        $user->rename($displayName);
        $this->entityManager->flush();
    }

    public function renameHousehold(Household $household, string $name): void
    {
        $household->rename($name);
        $this->entityManager->flush();
    }

    /**
     * Admin-Rechte vergeben oder entziehen. Geänderte Rollen melden laufende Sitzungen ab (Symfony vergleicht die
     * Rollen), „Angemeldet bleiben“ meldet sie gleich wieder an.
     */
    public function changeAdmin(string $email, bool $admin): User
    {
        $user = $this->users->findOneByEmail($email)
            ?? throw new \DomainException(\sprintf('Es gibt keinen Benutzer mit der E-Mail-Adresse "%s".', $email));

        $user->setAdmin($admin);
        $this->entityManager->flush();

        return $user;
    }

    private static function assertPasswordStrength(string $plainPassword): void
    {
        if (mb_strlen($plainPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('Das Passwort muss mindestens %d Zeichen lang sein.', self::MIN_PASSWORD_LENGTH));
        }
    }
}
