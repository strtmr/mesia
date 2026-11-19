<?php

declare(strict_types=1);

namespace App\Shared\Application\Sync;

use Symfony\Component\Uid\Uuid;

final readonly class MutationContext
{
    /**
     * @param \DateTimeImmutable $at  Zeitpunkt der Aktion auf dem Gerät (auf "jetzt" begrenzt, falls die Geräteuhr vorgeht)
     * @param \DateTimeImmutable $now Serverzeit
     */
    public function __construct(
        public Uuid $householdId,
        public Uuid $actorId,
        public Uuid $mutationId,
        public \DateTimeImmutable $at,
        public \DateTimeImmutable $now,
    ) {
    }
}
