<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure;

use App\Admin\Application\AdminStatistics;
use App\Admin\Application\ArticleUsage;
use App\Admin\Application\HouseholdOverview;
use App\Admin\Application\Totals;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\Uid\Uuid;

/**
 * Per SQL über die Tabellen der Fachmodule, ohne deren Klassen zu kennen (Modulgrenzen bleiben unberührt).
 */
#[AsAlias(AdminStatistics::class)]
final readonly class DbalAdminStatistics implements AdminStatistics
{
    public function __construct(private Connection $connection)
    {
    }

    public function totals(\DateTimeImmutable $purchasesSince): Totals
    {
        /** @var array{households: int, users: int, open_items: int, purchases: int} $row */
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT (SELECT count(*) FROM household) AS households,
                       (SELECT count(*) FROM app_user) AS users,
                       (SELECT count(*) FROM shopping_list_item WHERE checked_at IS NULL AND deleted_at IS NULL) AS open_items,
                       (SELECT count(*) FROM purchase_event WHERE purchased_at >= :since) AS purchases
                SQL,
            ['since' => $purchasesSince],
            ['since' => Types::DATETIMETZ_IMMUTABLE],
        );

        return new Totals((int) $row['households'], (int) $row['users'], (int) $row['open_items'], (int) $row['purchases']);
    }

    public function households(\DateTimeImmutable $purchasesSince): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT h.id, h.name,
                       (SELECT string_agg(u.display_name, E'\n' ORDER BY u.display_name) FROM app_user u WHERE u.household_id = h.id) AS members,
                       (SELECT count(*) FROM shopping_list_item i WHERE i.household_id = h.id AND i.checked_at IS NULL AND i.deleted_at IS NULL) AS open_items,
                       (SELECT count(*) FROM purchase_event p WHERE p.household_id = h.id AND p.purchased_at >= :since) AS purchases,
                       (SELECT c.created_at FROM change_log c WHERE c.household_id = h.id ORDER BY c.seq DESC LIMIT 1) AS last_activity
                FROM household h
                ORDER BY last_activity DESC NULLS LAST, h.name
                SQL,
            ['since' => $purchasesSince],
            ['since' => Types::DATETIMETZ_IMMUTABLE],
        );

        return array_map(fn (array $row): HouseholdOverview => new HouseholdOverview(
            Uuid::fromString((string) $row['id']),
            (string) $row['name'],
            null === $row['members'] ? [] : explode("\n", (string) $row['members']),
            (int) $row['open_items'],
            (int) $row['purchases'],
            null === $row['last_activity'] ? null : $this->toDateTime($row['last_activity']),
        ), $rows);
    }

    public function userArticles(int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                WITH labelled AS (
                    -- Aktuelle Bezeichnung: zuletzt geänderter Listeneintrag des Artikels, sonst der Artikelname
                    SELECT a.household_id, a.name_normalized, a.purchase_count, c.name AS category,
                           COALESCE(i.name, a.name) AS label,
                           COALESCE(i.updated_at, a.updated_at) AS label_at
                    FROM article a
                    LEFT JOIN category c ON c.id = a.category_id
                    LEFT JOIN LATERAL (
                        SELECT li.name, li.updated_at FROM shopping_list_item li
                        WHERE li.article_id = a.id AND li.deleted_at IS NULL
                        ORDER BY li.updated_at DESC LIMIT 1
                    ) i ON true
                    WHERE a.source = 'user' AND a.deleted_at IS NULL
                )
                SELECT (array_agg(label ORDER BY label_at DESC))[1] AS name,
                       mode() WITHIN GROUP (ORDER BY category) AS category,
                       count(DISTINCT household_id) AS households,
                       sum(purchase_count) AS purchases,
                       name_normalized AS key
                FROM labelled
                GROUP BY name_normalized
                ORDER BY households DESC, purchases DESC, name
                LIMIT :limit
                SQL,
            ['limit' => $limit],
            ['limit' => Types::INTEGER],
        );

        return array_map(self::usage(...), $rows);
    }

    public function userArticleRefs(string $key): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT household_id, id FROM article WHERE source = 'user' AND deleted_at IS NULL AND name_normalized = :key",
            ['key' => $key],
        );

        return array_map(static fn (array $row): array => [
            'householdId' => Uuid::fromString((string) $row['household_id']),
            'articleId' => Uuid::fromString((string) $row['id']),
        ], $rows);
    }

    public function topPurchases(?\DateTimeImmutable $since, ?Uuid $householdId, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT mode() WITHIN GROUP (ORDER BY p.name) AS name,
                       mode() WITHIN GROUP (ORDER BY c.name) AS category,
                       count(DISTINCT p.household_id) AS households,
                       count(*) AS purchases
                FROM purchase_event p
                LEFT JOIN article a ON a.id = p.article_id
                LEFT JOIN category c ON c.id = a.category_id
                WHERE (CAST(:since AS timestamptz) IS NULL OR p.purchased_at >= :since)
                  AND (CAST(:household AS uuid) IS NULL OR p.household_id = :household)
                GROUP BY COALESCE(a.name_normalized, lower(p.name))
                ORDER BY purchases DESC, name
                LIMIT :limit
                SQL,
            ['since' => $since, 'household' => $householdId, 'limit' => $limit],
            ['since' => Types::DATETIMETZ_IMMUTABLE, 'household' => UuidType::NAME, 'limit' => Types::INTEGER],
        );

        return array_map(self::usage(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function usage(array $row): ArticleUsage
    {
        return new ArticleUsage(
            (string) $row['name'],
            null === $row['category'] ? null : (string) $row['category'],
            (int) $row['households'],
            (int) $row['purchases'],
            isset($row['key']) ? (string) $row['key'] : null,
        );
    }

    private function toDateTime(mixed $value): \DateTimeImmutable
    {
        $converted = $this->connection->convertToPHPValue($value, Types::DATETIMETZ_IMMUTABLE);
        \assert($converted instanceof \DateTimeImmutable);

        return $converted;
    }
}
