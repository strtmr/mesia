<?php

declare(strict_types=1);

namespace App\Tests\Double\Shared;

use App\Shared\Domain\Syncable;
use Symfony\Component\Uid\Uuid;

final readonly class SyncableStub implements Syncable
{
    private Uuid $id;
    private Uuid $householdId;

    public function __construct(private bool $deleted = false)
    {
        $this->id = Uuid::v7();
        $this->householdId = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getHouseholdId(): Uuid
    {
        return $this->householdId;
    }

    public static function syncEntityType(): string
    {
        return 'stub';
    }

    public function toSyncSnapshot(): array
    {
        return ['name' => 'Milch'];
    }

    public function isSyncDeleted(): bool
    {
        return $this->deleted;
    }
}
