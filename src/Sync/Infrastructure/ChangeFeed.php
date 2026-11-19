<?php

declare(strict_types=1);

namespace App\Sync\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Liest das Änderungsprotokoll eines Haushalts für den Sync-Pull.
 */
final readonly class ChangeFeed
{
    public function __construct(private Connection $connection)
    {
    }

    public function currentCursor(Uuid $householdId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COALESCE(MAX(seq), 0) FROM change_log WHERE household_id = ?',
            [$householdId],
            [UuidType::NAME],
        );
    }

    /**
     * @return array{changes: list<array{seq: int, type: string, id: string, op: string, data: array<string, mixed>}>, cursor: int, has_more: bool}
     */
    public function since(Uuid $householdId, int $since, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT seq, entity_type, entity_id, operation, snapshot
               FROM change_log
              WHERE household_id = ? AND seq > ?
              ORDER BY seq
              LIMIT ?',
            [$householdId, $since, $limit + 1],
            [UuidType::NAME, ParameterType::INTEGER, ParameterType::INTEGER],
        );

        $hasMore = \count($rows) > $limit;
        $rows = \array_slice($rows, 0, $limit);

        $changes = array_map(static function (array $row): array {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) $row['snapshot'], true, flags: \JSON_THROW_ON_ERROR);

            return [
                'seq' => (int) $row['seq'],
                'type' => (string) $row['entity_type'],
                'id' => (string) $row['entity_id'],
                'op' => (string) $row['operation'],
                'data' => $data,
            ];
        }, $rows);

        return [
            'changes' => $changes,
            'cursor' => [] === $changes ? $since : $changes[array_key_last($changes)]['seq'],
            'has_more' => $hasMore,
        ];
    }
}
