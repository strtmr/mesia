<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Sortierschlüssel im Format der JS-Bibliothek "fractional-indexing" (Base62).
 *
 * Clients erzeugen Schlüssel zwischen zwei Nachbarn selbst. Der Server braucht nur eine Startreihenfolge
 * (z. B. beim Anlegen des Katalogs). Die Schlüssel werden als Text verglichen, in PostgreSQL mit Collation "C".
 */
final class SortKey
{
    private const string DIGITS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /** Erstes Zeichen = Länge des ganzzahligen Teils: A–Z negativ (lang → kurz), a–z positiv (kurz → lang) */
    private const string HEADS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * Liefert $count aufsteigende Schlüssel: a0, a1, …, az, b00, b01, ….
     *
     * @return list<string>
     */
    public static function sequence(int $count): array
    {
        $keys = [];
        $key = 'a0';
        for ($i = 0; $i < $count; ++$i) {
            $keys[] = $key;
            $key = self::incrementInteger($key);
        }

        return $keys;
    }

    public static function isValid(string $key): bool
    {
        return 1 === preg_match('/^[A-Za-z][0-9A-Za-z]{1,63}$/', $key);
    }

    /**
     * Port von incrementInteger() aus fractional-indexing.
     */
    private static function incrementInteger(string $key): string
    {
        $head = $key[0];
        $digits = str_split(substr($key, 1));

        for ($i = \count($digits) - 1; $i >= 0; --$i) {
            $next = strpos(self::DIGITS, $digits[$i]) + 1;
            if ($next < \strlen(self::DIGITS)) {
                $digits[$i] = self::DIGITS[$next];

                return $head.implode('', $digits);
            }
            $digits[$i] = self::DIGITS[0];
        }

        // Überlauf: nächste Länge
        if ('Z' === $head) {
            return 'a'.self::DIGITS[0];
        }
        if ('z' === $head) {
            throw new \OverflowException('Sortierschlüssel-Bereich erschöpft.');
        }

        $headPosition = strpos(self::HEADS, $head);
        if (false === $headPosition) {
            throw new \InvalidArgumentException(\sprintf('Ungültiger Sortierschlüssel "%s".', $key));
        }
        $nextHead = self::HEADS[$headPosition + 1];
        if ($nextHead > 'a') {
            $digits[] = self::DIGITS[0];
        } else {
            array_pop($digits);
        }

        return $nextHead.implode('', $digits);
    }
}
