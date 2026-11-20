<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Herkunft eines Artikels. Der Katalog-Abgleich (app:catalog:update) benennt nur Artikel aus dem Startkatalog um,
 * selbst angelegte bleiben unangetastet.
 */
enum ArticleSource: string
{
    /** Aus dem Startkatalog (data/seed/articles.yaml), beim Anlegen des Haushalts oder per Katalog-Abgleich */
    case Seed = 'seed';
    /** Vom Benutzer angelegt (unbekannter Begriff in der Schnelleingabe) */
    case User = 'user';
}
