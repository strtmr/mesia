<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Einheiten für Mengen auf der Einkaufsliste und in Rezepten.
 *
 * Die JS-Seite (Schnelleingabe) kennt dieselben Werte. Bei Änderungen beide Seiten anpassen.
 */
enum Unit: string
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Milliliter = 'ml';
    case Liter = 'l';
    case Piece = 'stk';
    case Pack = 'pck';
    case Bunch = 'bund';
    case Can = 'dose';
    case Bottle = 'flasche';
    case Jar = 'glas';
    case Cup = 'becher';
    case Tablespoon = 'el';
    case Teaspoon = 'tl';
    case Pinch = 'prise';

    public function label(): string
    {
        return match ($this) {
            self::Gram => 'g',
            self::Kilogram => 'kg',
            self::Milliliter => 'ml',
            self::Liter => 'l',
            self::Piece => 'Stk',
            self::Pack => 'Pck',
            self::Bunch => 'Bund',
            self::Can => 'Dose',
            self::Bottle => 'Fl.',
            self::Jar => 'Glas',
            self::Cup => 'Becher',
            self::Tablespoon => 'EL',
            self::Teaspoon => 'TL',
            self::Pinch => 'Prise',
        };
    }
}
