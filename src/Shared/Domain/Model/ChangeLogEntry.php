<?php

declare(strict_types=1);

namespace App\Shared\Domain\Model;

use App\Shared\Domain\ChangeLog\ChangeOperation;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Ein Eintrag im Änderungsprotokoll eines Haushalts.
 *
 * Geschrieben wird ausschließlich über {@see \App\Shared\Application\ChangeLog\ChangeRecorder} (per DBAL, innerhalb
 * der laufenden Transaktion). Das Mapping existiert, damit das Schema über Doctrine-Migrations verwaltet wird und
 * Einträge lesend abgefragt werden können.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'change_log')]
#[ORM\Index(name: 'change_log_household_seq_idx', columns: ['household_id', 'seq'])]
class ChangeLogEntry
{
    /**
     * Fortlaufende Nummer, dient den Clients als Sync-Cursor.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private int|string $seq;

    /**
     * @param array<string, mixed> $snapshot
     */
    public function __construct(
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $householdId,

        #[ORM\Column(length: 64)]
        private string $entityType,

        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $entityId,

        #[ORM\Column(length: 16, enumType: ChangeOperation::class)]
        private ChangeOperation $operation,

        #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
        private array $snapshot,

        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        private ?Uuid $actorId,

        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        private ?Uuid $mutationId,

        #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function getSeq(): int
    {
        return (int) $this->seq;
    }

    public function getHouseholdId(): Uuid
    {
        return $this->householdId;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): Uuid
    {
        return $this->entityId;
    }

    public function getOperation(): ChangeOperation
    {
        return $this->operation;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function getActorId(): ?Uuid
    {
        return $this->actorId;
    }

    public function getMutationId(): ?Uuid
    {
        return $this->mutationId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
