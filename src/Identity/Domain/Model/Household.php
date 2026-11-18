<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Identity\Infrastructure\Doctrine\HouseholdRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Ein Haushalt ist der Mandant: Alle fachlichen Daten (Liste, Katalog, Pläne) gehören genau einem Haushalt.
 */
#[ORM\Entity(repositoryClass: HouseholdRepository::class)]
#[ORM\Table(name: 'household')]
class Household
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 100)]
        private string $name,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
        $this->rename($name);
        $this->createdAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new \InvalidArgumentException('Der Name des Haushalts darf nicht leer sein.');
        }

        $this->name = $name;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
