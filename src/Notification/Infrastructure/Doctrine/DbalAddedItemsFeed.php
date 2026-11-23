<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine;

use App\Notification\Application\AddedItem;
use App\Notification\Application\AddedItemsFeed;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Uid\Uuid;

/**
 * Liest aus dem Änderungsprotokoll, welche Einträge durch "item.add" oder "item.reactivate" entstanden sind
 * (verknüpft über die Mutation) und wer sie ausgelöst hat.
 */
#[AsAlias(AddedItemsFeed::class)]
final readonly class DbalAddedItemsFeed implements AddedItemsFeed
{
    public function __construct(private Connection $connection)
    {
    }

    public function addedByOthers(Uuid $householdId, int $afterSeq, Uuid $excludeUserId): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT c.seq, c.snapshot->>'name' AS name, u.display_name AS actor
            FROM change_log c
            JOIN processed_mutation p ON p.mutation_id = c.mutation_id
            LEFT JOIN app_user u ON u.id = c.actor_id
            WHERE c.household_id = :household
              AND c.seq > :after
              AND c.entity_type = 'shopping_item'
              AND p.type IN ('item.add', 'item.reactivate')
              AND p.status IN ('applied', 'merged')
              AND (c.actor_id IS NULL OR c.actor_id <> :user)
            ORDER BY c.seq
            SQL, [
            'household' => $householdId->toRfc4122(),
            'after' => $afterSeq,
            'user' => $excludeUserId->toRfc4122(),
        ]);

        return array_map(static fn (array $row): AddedItem => new AddedItem(
            (int) $row['seq'],
            (string) $row['name'],
            null === $row['actor'] ? null : (string) $row['actor'],
        ), $rows);
    }

    public function latestSeq(Uuid $householdId): int
    {
        return (int) $this->connection->fetchOne('SELECT COALESCE(MAX(seq), 0) FROM change_log WHERE household_id = ?', [$householdId->toRfc4122()]);
    }
}
