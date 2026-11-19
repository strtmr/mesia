<?php

declare(strict_types=1);

namespace App\Shopping\Domain;

/**
 * Hilfsfunktionen für Mengen. Doctrine liefert DECIMAL-Spalten als String, damit nichts gerundet wird.
 */
final class Quantity
{
    public const float MAX = 99999;

    public static function fromFloat(?float $value): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value <= 0 || $value > self::MAX) {
            throw new \InvalidArgumentException('Die Menge muss größer als 0 und höchstens 99999 sein.');
        }

        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    public static function toFloat(?string $value): ?float
    {
        return null === $value ? null : (float) $value;
    }
}
