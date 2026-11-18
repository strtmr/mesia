<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Model\Household;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Doctrine\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Legt Haushalte und Benutzer an. In Phase 0/1 nur über Konsolenbefehle, eine Selbstregistrierung gibt es noch nicht.
 */
final readonly class UserRegistration
{
    public const int MIN_PASSWORD_LENGTH = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
        private UserPasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
    ) {
    }

    public function createHousehold(string $name): Household
    {
        $household = new Household($name, $this->clock->now());
        $this->entityManager->persist($household);
        $this->entityManager->flush();

        return $household;
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
     * "Angemeldet bleiben"-Cookies dieses Benutzers ungültig.
     */
    public function changePassword(string $email, string $plainPassword): void
    {
        $user = $this->users->findOneByEmail($email)
            ?? throw new \DomainException(\sprintf('Es gibt keinen Benutzer mit der E-Mail-Adresse "%s".', $email));

        self::assertPasswordStrength($plainPassword);

        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();
    }

    private static function assertPasswordStrength(string $plainPassword): void
    {
        if (mb_strlen($plainPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('Das Passwort muss mindestens %d Zeichen lang sein.', self::MIN_PASSWORD_LENGTH));
        }
    }
}
