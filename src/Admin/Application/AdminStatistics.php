<?php

declare(strict_types=1);

namespace App\Admin\Application;

use Symfony\Component\Uid\Uuid;

/**
 * Auswertungen über alle Haushalte für den Admin-Bereich. Nur lesend.
 */
interface AdminStatistics
{
    public function totals(\DateTimeImmutable $purchasesSince): Totals;

    /**
     * @return list<HouseholdOverview> zuletzt aktive zuerst
     */
    public function households(\DateTimeImmutable $purchasesSince): array;

    /**
     * Selbst angelegte Artikel (nicht aus dem Startkatalog), über alle Haushalte nach Normalform zusammengefasst.
     * Was in mehreren Haushalten auftaucht oder oft gekauft wird, ist ein Kandidat für den Startkatalog.
     *
     * @return list<ArticleUsage>
     */
    public function userArticles(int $limit): array;

    /**
     * Meistgekaufte Artikel (Kaufhistorie), nach Normalform zusammengefasst.
     *
     * @return list<ArticleUsage>
     */
    public function topPurchases(?\DateTimeImmutable $since, ?Uuid $householdId, int $limit): array;
}
