<?php

declare(strict_types=1);

namespace App\Sync\Application;

use App\Shared\Application\Sync\SnapshotProvider;
use App\Sync\Infrastructure\ChangeFeed;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Uid\Uuid;

/**
 * Erstbefüllung eines Geräts: alle synchronisierten Objekte des Haushalts plus Cursor für den folgenden Pull.
 *
 * Der Cursor wird VOR den Daten gelesen. Änderungen dazwischen kommen beim nächsten Pull ggf. doppelt an. Das ist
 * unschädlich, weil Clients per Upsert übernehmen. Umgekehrt geht nichts verloren.
 */
final readonly class SnapshotBuilder
{
    /**
     * @param iterable<SnapshotProvider> $providers
     */
    public function __construct(
        #[AutowireIterator(SnapshotProvider::TAG)]
        private iterable $providers,
        private ChangeFeed $changeFeed,
    ) {
    }

    /**
     * @return array{cursor: int, entities: array<string, list<array<string, mixed>>>}
     */
    public function build(Uuid $householdId): array
    {
        $cursor = $this->changeFeed->currentCursor($householdId);
        $entities = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->syncablesFor($householdId) as $syncable) {
                $entities[$syncable::syncEntityType()][] = $syncable->toSyncSnapshot();
            }
        }

        return ['cursor' => $cursor, 'entities' => $entities];
    }
}
