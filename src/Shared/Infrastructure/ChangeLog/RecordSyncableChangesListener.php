<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ChangeLog;

use App\Shared\Application\ChangeLog\ChangeContext;
use App\Shared\Application\ChangeLog\ChangeRecord;
use App\Shared\Application\ChangeLog\ChangeRecorder;
use App\Shared\Domain\ChangeLog\ChangeOperation;
use App\Shared\Domain\Syncable;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Clock\ClockInterface;

/**
 * Protokolliert jede Änderung an einem {@see Syncable} automatisch.
 *
 * Doctrine löst postPersist/postUpdate/postRemove innerhalb der flush()-Transaktion aus. Daten und Protokolleintrag
 * werden also gemeinsam committet oder gemeinsam verworfen.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final readonly class RecordSyncableChangesListener
{
    public function __construct(
        private ChangeRecorder $recorder,
        private ChangeContext $context,
        private ClockInterface $clock,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->recordChange($args->getObject(), removed: false);
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->recordChange($args->getObject(), removed: false);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->recordChange($args->getObject(), removed: true);
    }

    private function recordChange(object $entity, bool $removed): void
    {
        if (!$entity instanceof Syncable) {
            return;
        }

        $this->recorder->record(new ChangeRecord(
            householdId: $entity->getHouseholdId(),
            entityType: $entity::syncEntityType(),
            entityId: $entity->getId(),
            operation: $removed || $entity->isSyncDeleted() ? ChangeOperation::Delete : ChangeOperation::Upsert,
            snapshot: $entity->toSyncSnapshot(),
            actorId: $this->context->actorId(),
            mutationId: $this->context->mutationId(),
            occurredAt: $this->clock->now(),
        ));
    }
}
