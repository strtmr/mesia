<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Identity\Infrastructure\Doctrine\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'app_user_email_unique', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const string ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 100)]
    private string $displayName;

    #[ORM\Column(length: 255)]
    private string $password = '';

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * Zusätzliche Rollen neben ROLE_USER, derzeit nur ROLE_ADMIN (Admin-Bereich, vergeben per app:user:admin).
     *
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $roles = [];

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Household $household,
        string $email,
        string $displayName,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
        $this->email = self::normalizeEmail($email);
        $this->rename($displayName);
        $this->createdAt = $createdAt;
    }

    /**
     * E-Mail-Adressen werden kleingeschrieben gespeichert, damit der Login nicht an Groß-/Kleinschreibung scheitert.
     */
    public static function normalizeEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(\sprintf('"%s" ist keine gültige E-Mail-Adresse.', $email));
        }

        return $email;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getHousehold(): Household
    {
        return $this->household;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function rename(string $displayName): void
    {
        $displayName = trim($displayName);
        if ('' === $displayName) {
            throw new \InvalidArgumentException('Der Anzeigename darf nicht leer sein.');
        }

        $this->displayName = $displayName;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUserIdentifier(): string
    {
        \assert('' !== $this->email);

        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique(['ROLE_USER', ...$this->roles]));
    }

    public function isAdmin(): bool
    {
        return \in_array(self::ROLE_ADMIN, $this->roles, true);
    }

    public function setAdmin(bool $admin): void
    {
        $this->roles = $admin ? [self::ROLE_ADMIN] : [];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->password = $passwordHash;
    }

    /**
     * Im Session-Speicher nur einen Hash des Passwort-Hashes ablegen (Symfony-Empfehlung ab 7.3).
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
