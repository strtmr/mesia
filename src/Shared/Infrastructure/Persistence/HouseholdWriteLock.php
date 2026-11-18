<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Serialisiert schreibende Transaktionen eines Haushalts.
 *
 * Nutzt ein PostgreSQL-Advisory-Lock auf Transaktionsebene (pg_advisory_xact_lock): Es wird beim Commit oder Rollback
 * automatisch freigegeben und ist an genau die Transaktion gebunden, deren Reihenfolge es schützen soll. Ein
 * Symfony-Lock (LockFactory) würde den Lock vom Transaktionsende entkoppeln.
 *
 * Warum? Sequenznummern im Änderungsprotokoll werden beim INSERT vergeben, sichtbar werden sie aber erst beim
 * Commit. Ohne Serialisierung könnte Nummer 11 vor Nummer 10 sichtbar werden. Ein Client, der danach "seq > 11"
 * abfragt, würde Nummer 10 nie sehen.
 *
 * Schreibende Anwendungsfälle (z. B. Sync-Push) sollen den Lock gleich zu Beginn ihrer Transaktion holen. Dann kann
 * es keine Deadlocks mit Zeilensperren geben. Der ChangeRecorder holt ihn zur Sicherheit noch einmal. Mehrfaches
 * Anfordern in derselben Transaktion ist unproblematisch.
 */
final readonly class HouseholdWriteLock
{
    public function __construct(private Connection $connection)
    {
    }

    public function acquire(Uuid $householdId): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw new \LogicException('HouseholdWriteLock::acquire() must be called inside a database transaction.');
        }

        $this->connection->executeStatement(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
            [$householdId->toRfc4122()],
        );
    }
}
