import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { normalize } from '../../assets/shopping/core/normalize.js';

// Dieselben Fälle prüft tests/Unit/Catalog/TextNormalizerTest.php für die PHP-Variante.
const cases = JSON.parse(readFileSync(new URL('../fixtures/normalize.json', import.meta.url), 'utf8'));

describe('normalize', () => {
    it.each(cases)('$input → $expected', ({ input, expected }) => {
        expect(normalize(input)).toBe(expected);
    });
});
