<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\ChangeLog;

use App\Shared\Application\ChangeLog\ChangeContext;
use App\Shared\Domain\ChangeLog\ChangeOperation;
use App\Shared\Infrastructure\ChangeLog\RecordSyncableChangesListener;
use App\Tests\Double\Shared\InMemoryChangeRecorder;
use App\Tests\Double\Shared\SyncableStub;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class RecordSyncableChangesListenerTest extends TestCase
{
    private InMemoryChangeRecorder $recorder;
    private ChangeContext $context;
    private MockClock $clock;
    private RecordSyncableChangesListener $listener;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->recorder = new InMemoryChangeRecorder();
        $this->context = new ChangeContext();
        $this->clock = new MockClock('2026-11-20 17:03:11');
        $this->listener = new RecordSyncableChangesListener($this->recorder, $this->context, $this->clock);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
    }

    public function testPersistOfSyncableIsRecordedAsUpsertWithContext(): void
    {
        $entity = new SyncableStub();
        $actorId = Uuid::v7();
        $mutationId = Uuid::v7();
        $this->context->setActorId($actorId);

        $this->context->runForMutation(
            $mutationId,
            fn () => $this->listener->postPersist(new PostPersistEventArgs($entity, $this->entityManager)),
        );

        self::assertCount(1, $this->recorder->records);
        $record = $this->recorder->records[0];
        self::assertTrue($entity->getHouseholdId()->equals($record->householdId));
        self::assertTrue($entity->getId()->equals($record->entityId));
        self::assertSame('stub', $record->entityType);
        self::assertSame(ChangeOperation::Upsert, $record->operation);
        self::assertSame(['name' => 'Milch'], $record->snapshot);
        self::assertSame($actorId, $record->actorId);
        self::assertSame($mutationId, $record->mutationId);
        self::assertEquals($this->clock->now(), $record->occurredAt);
    }

    public function testSoftDeletedSyncableIsRecordedAsDelete(): void
    {
        $entity = new SyncableStub(deleted: true);

        $this->listener->postUpdate(new PostUpdateEventArgs($entity, $this->entityManager));

        self::assertSame(ChangeOperation::Delete, $this->recorder->records[0]->operation);
    }

    public function testRemovedSyncableIsRecordedAsDelete(): void
    {
        $this->listener->postRemove(new PostRemoveEventArgs(new SyncableStub(), $this->entityManager));

        self::assertSame(ChangeOperation::Delete, $this->recorder->records[0]->operation);
    }

    public function testOtherEntitiesAreIgnored(): void
    {
        $this->listener->postPersist(new PostPersistEventArgs(new \stdClass(), $this->entityManager));

        self::assertSame([], $this->recorder->records);
    }
}
