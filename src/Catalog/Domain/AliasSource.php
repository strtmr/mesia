<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

enum AliasSource: string
{
    /** Aus dem Startkatalog (data/seed/articles.yaml) */
    case Seed = 'seed';
    /** Vom Benutzer gelernt (Freitext einem Artikel zugeordnet) */
    case User = 'user';
}
