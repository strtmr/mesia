<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

use Symfony\Component\Uid\Uuid;

/**
 * Ein Haushalt wurde angelegt (oder soll nachträglich initialisiert werden).
 *
 * Module reagieren darauf, ohne dass Identity sie kennen muss: Catalog legt den Startkatalog an, Shopping die Liste.
 * Listener müssen idempotent sein: ein zweites Ereignis für denselben Haushalt darf nichts doppelt anlegen.
 * Das Ereignis wird innerhalb der Transaktion ausgelöst, die den Haushalt anlegt.
 */
final readonly class HouseholdCreated
{
    public function __construct(public Uuid $householdId)
    {
    }
}
