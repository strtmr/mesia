<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Gemeinsame Felder aller synchronisierten Entities: Haushalt, Version, Zeitstempel, Soft Delete.
 *
 * Gelöscht wird nur weich (deleted_at), damit die Löschung über den Sync auf allen Geräten ankommt.
 */
trait SyncableFields
{
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $householdId;

    /** Erhöht Doctrine bei jedem Update automatisch (Optimistic Locking). */
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function getHouseholdId(): Uuid
    {
        return $this->householdId;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isSyncDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function softDelete(\DateTimeImmutable $now): void
    {
        $this->deletedAt ??= $now;
        $this->touch($now);
    }

    private function initSyncableFields(Uuid $householdId, \DateTimeImmutable $now): void
    {
        $this->householdId = $householdId;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    private function touch(\DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }

    /**
     * Felder, die jeder Snapshot enthält.
     *
     * @return array{version: int, updated_at: string, deleted: bool}
     */
    private function syncMeta(): array
    {
        return [
            'version' => $this->version,
            'updated_at' => $this->updatedAt->format(\DATE_ATOM),
            'deleted' => null !== $this->deletedAt,
        ];
    }
}
