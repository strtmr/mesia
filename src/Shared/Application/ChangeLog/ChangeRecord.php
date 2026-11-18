<?php

declare(strict_types=1);

namespace App\Shared\Application\ChangeLog;

use App\Shared\Domain\ChangeLog\ChangeOperation;
use Symfony\Component\Uid\Uuid;

final readonly class ChangeRecord
{
    /**
     * @param array<string, mixed> $snapshot
     */
    public function __construct(
        public Uuid $householdId,
        public string $entityType,
        public Uuid $entityId,
        public ChangeOperation $operation,
        public array $snapshot,
        public ?Uuid $actorId,
        public ?Uuid $mutationId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
