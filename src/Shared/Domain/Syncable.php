<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Symfony\Component\Uid\Uuid;

/**
 * Entities, die mit den Clients synchronisiert werden.
 *
 * Jede Änderung an einem Syncable landet automatisch im Änderungsprotokoll (change_log), siehe
 * {@see \App\Shared\Infrastructure\ChangeLog\RecordSyncableChangesListener}.
 */
interface Syncable
{
    public function getId(): Uuid;

    public function getHouseholdId(): Uuid;

    /**
     * Stabiler Typname für Protokoll und Sync-API, z. B. "shopping_item".
     */
    public static function syncEntityType(): string;

    /**
     * Vollständiger Zustand nach der Änderung, so wie ihn die Clients erhalten.
     *
     * @return array<string, mixed>
     */
    public function toSyncSnapshot(): array;

    /**
     * true, wenn die Entity als gelöscht gilt (Soft Delete).
     */
    public function isSyncDeleted(): bool;
}
