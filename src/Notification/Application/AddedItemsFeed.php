<?php

declare(strict_types=1);

namespace App\Notification\Application;

use Symfony\Component\Uid\Uuid;

/**
 * Was andere seit einem Punkt im Änderungsprotokoll auf die Liste gesetzt haben.
 */
interface AddedItemsFeed
{
    /**
     * @return list<AddedItem> hinzugefügte oder reaktivierte Einträge, nicht vom Benutzer selbst, aufsteigend
     */
    public function addedByOthers(Uuid $householdId, int $afterSeq, Uuid $excludeUserId): array;

    /** Letzter Eintrag im Änderungsprotokoll des Haushalts (0, wenn leer) */
    public function latestSeq(Uuid $householdId): int;
}
