<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use Symfony\Component\Uid\Uuid;

/**
 * Eine Kategorie wurde gelöscht. Andere Module verschieben ihre Einträge in die Fallback-Kategorie ("Sonstiges").
 * Wird innerhalb der Transaktion ausgelöst, alles passiert also gemeinsam oder gar nicht.
 */
final readonly class CategoryDeleted
{
    public function __construct(
        public Uuid $householdId,
        public Uuid $categoryId,
        public Uuid $fallbackCategoryId,
        public \DateTimeImmutable $now,
    ) {
    }
}
