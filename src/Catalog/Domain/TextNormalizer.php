<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Normalform für Artikelnamen und Aliase, damit "TK-Erbsen", "tk erbsen" und "TK Erbsen" gleich behandelt werden.
 *
 * WICHTIG: Die JavaScript-Variante in assets/shopping/core/normalize.js muss exakt dieselben Regeln umsetzen.
 * Beide werden gegen tests/fixtures/normalize.json getestet.
 *
 * Bewusst ohne ext-intl und iconv, damit das Ergebnis auf jedem System und in JavaScript identisch ist.
 */
final class TextNormalizer
{
    private const array TRANSLITERATION = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'æ' => 'ae',
        'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'œ' => 'oe',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
    ];

    public function normalize(string $text): string
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');
        // Kombinierende diakritische Zeichen (zerlegte Unicode-Form) entfernen.
        $normalized = (string) preg_replace('/[\x{0300}-\x{036F}]/u', '', $normalized);
        $normalized = strtr($normalized, self::TRANSLITERATION);
        $normalized = (string) preg_replace('/[^a-z0-9%]+/', ' ', $normalized);

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }
}
