<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\CatalogSeeder;
use App\Shopping\Application\CreateDefaultStoresOnHouseholdCreated;
use App\Tests\Support\CreatesUsers;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class HouseholdInitializationTest extends KernelTestCase
{
    use CreatesUsers;

    public function testNewHouseholdGetsStarterCatalogShoppingListAndChangeLog(): void
    {
        self::bootKernel();
        $householdId = self::createUser()->getHousehold()->getId();

        self::assertSame(17, $this->rowCount('category', $householdId));
        self::assertSame(1, $this->rowCount('category', $householdId, 'is_fallback = true'));
        self::assertSame(365, $this->rowCount('article', $householdId));
        self::assertSame(352, $this->rowCount('article_alias', $householdId));
        self::assertSame(1, $this->rowCount('shopping_list', $householdId));
        $stores = \count(CreateDefaultStoresOnHouseholdCreated::DEFAULT_STORES);
        self::assertSame($stores, $this->rowCount('store', $householdId));
        self::assertSame(17 + 365 + 352 + 1 + $stores, $this->rowCount('change_log', $householdId), 'Jedes angelegte Objekt steht im Änderungsprotokoll.');
    }

    public function testSeedingIsIdempotent(): void
    {
        self::bootKernel();
        $householdId = self::createUser()->getHousehold()->getId();

        $seededAgain = self::getContainer()->get(CatalogSeeder::class)->seed($householdId);

        self::assertFalse($seededAgain);
        self::assertSame(17, $this->rowCount('category', $householdId));
    }

    private function rowCount(string $table, Uuid $householdId, string $extraCondition = 'true'): int
    {
        return (int) self::getContainer()->get(Connection::class)->fetchOne(
            \sprintf('SELECT COUNT(*) FROM %s WHERE household_id = ? AND %s', $table, $extraCondition),
            [$householdId->toRfc4122()],
        );
    }
}
