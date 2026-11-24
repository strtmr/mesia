<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use Symfony\Component\Uid\Uuid;

/**
 * Ein Artikel wurde aus dem Katalog gelöscht. Andere Module räumen ihre Verweise auf (z. B. „Erledigt“).
 * Wird innerhalb der Transaktion ausgelöst.
 */
final readonly class ArticleDeleted
{
    public function __construct(
        public Uuid $householdId,
        public Uuid $articleId,
        public \DateTimeImmutable $now,
    ) {
    }
}
