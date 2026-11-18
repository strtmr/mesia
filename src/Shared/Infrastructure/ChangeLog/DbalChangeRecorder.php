<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ChangeLog;

use App\Shared\Application\ChangeLog\ChangeRecord;
use App\Shared\Application\ChangeLog\ChangeRecorder;
use App\Shared\Infrastructure\Persistence\HouseholdWriteLock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Schreibt per DBAL statt per ORM, weil der Aufruf mitten in einem flush() passiert (Doctrine-Lifecycle-Events),
 * wo keine neuen Entities mehr persistiert werden können.
 */
#[AsAlias(ChangeRecorder::class)]
final readonly class DbalChangeRecorder implements ChangeRecorder
{
    public function __construct(
        private Connection $connection,
        private HouseholdWriteLock $householdWriteLock,
    ) {
    }

    public function record(ChangeRecord $record): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new \LogicException('Change log entries must be written inside the transaction of the change itself.');
        }

        $this->householdWriteLock->acquire($record->householdId);

        $this->connection->insert(
            'change_log',
            [
                'household_id' => $record->householdId,
                'entity_type' => $record->entityType,
                'entity_id' => $record->entityId,
                'operation' => $record->operation->value,
                'snapshot' => $record->snapshot,
                'actor_id' => $record->actorId,
                'mutation_id' => $record->mutationId,
                'created_at' => $record->occurredAt,
            ],
            [
                'household_id' => UuidType::NAME,
                'entity_id' => UuidType::NAME,
                'snapshot' => Types::JSON,
                'actor_id' => UuidType::NAME,
                'mutation_id' => UuidType::NAME,
                'created_at' => Types::DATETIMETZ_IMMUTABLE,
            ],
        );
    }
}
