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
     * Angezeigt wird die aktuelle Bezeichnung, also wie der Eintrag zuletzt auf einer Liste hieß (umbenannt wird in
     * der App nur der Eintrag, nicht der Katalogartikel).
     *
     * @return list<ArticleUsage>
     */
    public function userArticles(int $limit): array;

    /**
     * Die einzelnen selbst angelegten Artikel hinter einem Eintrag von userArticles() (zum Löschen).
     *
     * @return list<array{householdId: Uuid, articleId: Uuid}>
     */
    public function userArticleRefs(string $key): array;

    /**
     * Meistgekaufte Artikel (Kaufhistorie), nach Normalform zusammengefasst.
     *
     * @return list<ArticleUsage>
     */
    public function topPurchases(?\DateTimeImmutable $since, ?Uuid $householdId, int $limit): array;
}
