<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\ChangeLog;

use App\Shared\Application\ChangeLog\ChangeRecord;
use App\Shared\Application\ChangeLog\ChangeRecorder;
use App\Shared\Domain\ChangeLog\ChangeOperation;
use App\Shared\Domain\Model\ChangeLogEntry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Läuft gegen die echte PostgreSQL-Testdatenbank. DAMADoctrineTestBundle kapselt jeden Test auf Treiberebene in
 * einer Transaktion und rollt sie danach zurück. Für DBAL ist dabei keine Transaktion aktiv.
 */
final class DbalChangeRecorderTest extends KernelTestCase
{
    public function testRecordsAreStoredWithIncreasingSequenceAndCanBeReadAsEntities(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $recorder = $container->get(ChangeRecorder::class);
        $entityManager = $container->get(EntityManagerInterface::class);
        $connection = $container->get(Connection::class);

        $householdId = Uuid::v7();
        $actorId = Uuid::v7();
        $occurredAt = new \DateTimeImmutable('2026-11-20 17:03:11+01:00');

        // Im echten Betrieb öffnet flush() die Transaktion. Hier übernimmt das der Test.
        $connection->transactional(function () use ($recorder, $householdId, $actorId, $occurredAt): void {
            $recorder->record($this->record($householdId, ChangeOperation::Upsert, ['name' => 'Milch', 'qty' => 2], $actorId, $occurredAt));
            $recorder->record($this->record($householdId, ChangeOperation::Delete, ['name' => 'Milch'], null, $occurredAt));
            $recorder->record($this->record(Uuid::v7(), ChangeOperation::Upsert, ['name' => 'Brot'], null, $occurredAt));
        });

        /** @var list<ChangeLogEntry> $entries */
        $entries = $entityManager->getRepository(ChangeLogEntry::class)->findBy(['householdId' => $householdId], ['seq' => 'ASC']);

        self::assertCount(2, $entries);
        self::assertGreaterThan($entries[0]->getSeq(), $entries[1]->getSeq());
        self::assertSame(ChangeOperation::Upsert, $entries[0]->getOperation());
        // assertEquals statt assertSame: jsonb speichert Schlüssel in eigener Reihenfolge.
        self::assertEquals(['name' => 'Milch', 'qty' => 2], $entries[0]->getSnapshot());
        self::assertTrue($actorId->equals($entries[0]->getActorId()));
        self::assertSame('shopping_item', $entries[0]->getEntityType());
        self::assertEquals($occurredAt, $entries[0]->getCreatedAt());
        self::assertSame(ChangeOperation::Delete, $entries[1]->getOperation());
        self::assertNull($entries[1]->getActorId());
    }

    public function testRefusesToWriteOutsideOfATransaction(): void
    {
        self::bootKernel();
        $recorder = self::getContainer()->get(ChangeRecorder::class);

        $this->expectException(\LogicException::class);
        $recorder->record($this->record(Uuid::v7(), ChangeOperation::Upsert, [], null, new \DateTimeImmutable()));
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function record(Uuid $householdId, ChangeOperation $operation, array $snapshot, ?Uuid $actorId, \DateTimeImmutable $occurredAt): ChangeRecord
    {
        return new ChangeRecord(
            householdId: $householdId,
            entityType: 'shopping_item',
            entityId: Uuid::v7(),
            operation: $operation,
            snapshot: $snapshot,
            actorId: $actorId,
            mutationId: Uuid::v7(),
            occurredAt: $occurredAt,
        );
    }
}
