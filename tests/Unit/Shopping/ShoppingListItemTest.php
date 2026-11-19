<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shopping;

use App\Catalog\Domain\Unit;
use App\Shopping\Domain\ContributionSource;
use App\Shopping\Domain\Model\ShoppingListItem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ShoppingListItemTest extends TestCase
{
    private Uuid $actor;

    protected function setUp(): void
    {
        $this->actor = Uuid::v7();
    }

    public function testCheckIsIgnoredWhenOlderThanTheLastReactivation(): void
    {
        $item = $this->item(at: '10:00');
        self::assertTrue($item->check($this->actor, $this->time('10:05'), $this->time('10:05')));

        $item->reactivate(Uuid::v7(), ContributionSource::Manual, null, null, null, $this->actor, $this->time('10:20'), $this->time('10:20'));

        // Offline um 10:10 abgehakt, aber erst jetzt synchronisiert: älter als das Reaktivieren → ignorieren
        self::assertFalse($item->check($this->actor, $this->time('10:10'), $this->time('10:30')));
        self::assertFalse($item->isChecked());

        self::assertTrue($item->check($this->actor, $this->time('10:25'), $this->time('10:30')));
    }

    public function testCheckInTheSameSecondAsReactivationIsAccepted(): void
    {
        $item = $this->item(at: '10:00');

        self::assertTrue($item->check($this->actor, $this->time('10:00'), $this->time('10:00')));
    }

    public function testAddingToACheckedItemReactivatesItAndArchivesOldContributions(): void
    {
        $item = $this->item(at: '10:00', quantity: '2', unit: Unit::Liter);
        $item->overrideQuantity('3', Unit::Liter, $this->actor, $this->time('10:01'));
        $item->check($this->actor, $this->time('10:05'), $this->time('10:05'));

        $item->addContribution(Uuid::v7(), ContributionSource::Manual, null, '1', Unit::Liter, $this->actor, $this->time('11:00'), $this->time('11:00'));

        self::assertFalse($item->isChecked());
        self::assertCount(1, $item->activeContributions());
        self::assertSame(['quantity' => '1', 'unit' => Unit::Liter], $item->effectiveQuantity(), 'Manuelle Menge wird beim Reaktivieren zurückgesetzt.');
    }

    public function testQuantitiesWithTheSameUnitAreSummed(): void
    {
        $item = $this->item(at: '10:00', quantity: '500', unit: Unit::Gram);
        $item->addContribution(Uuid::v7(), ContributionSource::Manual, null, '250', Unit::Gram, $this->actor, $this->time('10:01'), $this->time('10:01'));

        self::assertSame(['quantity' => '750', 'unit' => Unit::Gram], $item->effectiveQuantity());
    }

    public function testDifferentUnitsHaveNoCombinedQuantity(): void
    {
        $item = $this->item(at: '10:00', quantity: '500', unit: Unit::Gram);
        $item->addContribution(Uuid::v7(), ContributionSource::Manual, null, '1', Unit::Pack, $this->actor, $this->time('10:01'), $this->time('10:01'));

        self::assertNull($item->effectiveQuantity());
        self::assertCount(2, $item->toSyncSnapshot()['contributions']);
    }

    public function testUncheckKeepsContributions(): void
    {
        $item = $this->item(at: '10:00', quantity: '2', unit: Unit::Liter);
        $item->check($this->actor, $this->time('10:05'), $this->time('10:05'));

        self::assertTrue($item->uncheck($this->actor, $this->time('10:06')));
        self::assertFalse($item->uncheck($this->actor, $this->time('10:07')));
        self::assertSame(['quantity' => '2', 'unit' => Unit::Liter], $item->effectiveQuantity());
    }

    public function testMoveReportsCategoryChange(): void
    {
        $item = $this->item(at: '10:00');
        $sameCategory = $item->getCategoryId();

        self::assertFalse($item->moveTo($sameCategory, 'a5', $this->actor, $this->time('10:01')));
        self::assertTrue($item->moveTo(Uuid::v7(), 'a6', $this->actor, $this->time('10:02')));
    }

    private function item(string $at, ?string $quantity = null, ?Unit $unit = null): ShoppingListItem
    {
        $item = new ShoppingListItem(Uuid::v7(), Uuid::v7(), Uuid::v7(), Uuid::v7(), 'Milch', Uuid::v7(), 'a0', null, $this->actor, $this->time($at), $this->time($at));
        $item->addContribution(Uuid::v7(), ContributionSource::Manual, null, $quantity, $unit, $this->actor, $this->time($at), $this->time($at));

        return $item;
    }

    private function time(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-20 '.$time.':00+01:00');
    }
}
