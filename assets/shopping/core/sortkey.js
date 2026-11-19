import { generateKeyBetween } from 'fractional-indexing';

/**
 * Sortierschlüssel zwischen zwei Nachbarn (je null = Anfang/Ende), Format wie src/Shared/Domain/SortKey.php.
 * Haben zwei Geräte zufällig denselben Schlüssel vergeben, wird hinter dem vorderen Nachbarn einsortiert.
 *
 * @param {string|null} before
 * @param {string|null} after
 */
export function keyBetween(before, after) {
    if (before !== null && after !== null && before >= after) {
        return generateKeyBetween(before, null);
    }

    return generateKeyBetween(before, after);
}

/** Vergleich in Byte-Reihenfolge (wie Collation "C" in PostgreSQL). */
export function compareKeys(a, b) {
    if (a === b) {
        return 0;
    }

    return a < b ? -1 : 1;
}
