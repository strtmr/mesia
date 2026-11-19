<?php

declare(strict_types=1);

namespace App\Shared\Application\Sync;

use App\Shared\Domain\Syncable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Uid\Uuid;

/**
 * Liefert alle nicht gelöschten synchronisierten Objekte eines Moduls für die Erstbefüllung eines Geräts.
 */
#[AutoconfigureTag(self::TAG)]
interface SnapshotProvider
{
    public const string TAG = 'app.sync.snapshot_provider';

    /**
     * @return iterable<Syncable>
     */
    public function syncablesFor(Uuid $householdId): iterable;
}
