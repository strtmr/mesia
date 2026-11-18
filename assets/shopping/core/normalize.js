/**
 * Normalform für Artikelnamen und Aliase, damit "TK-Erbsen", "tk erbsen" und "TK Erbsen" gleich behandelt werden.
 *
 * WICHTIG: Muss exakt dieselben Regeln umsetzen wie src/Catalog/Domain/TextNormalizer.php.
 * Beide werden gegen tests/fixtures/normalize.json getestet.
 */

const TRANSLITERATION = {
    'ä': 'ae', 'ö': 'oe', 'ü': 'ue', 'ß': 'ss',
    'à': 'a', 'á': 'a', 'â': 'a', 'ã': 'a', 'å': 'a', 'æ': 'ae',
    'ç': 'c',
    'è': 'e', 'é': 'e', 'ê': 'e', 'ë': 'e',
    'ì': 'i', 'í': 'i', 'î': 'i', 'ï': 'i',
    'ñ': 'n',
    'ò': 'o', 'ó': 'o', 'ô': 'o', 'õ': 'o', 'ø': 'o', 'œ': 'oe',
    'ù': 'u', 'ú': 'u', 'û': 'u',
    'ý': 'y', 'ÿ': 'y',
};

/**
 * @param {string} text
 * @returns {string}
 */
export function normalize(text) {
    return text
        .trim()
        .toLowerCase()
        // Kombinierende diakritische Zeichen (zerlegte Unicode-Form) entfernen.
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^\u0000-\u007f]/g, (char) => TRANSLITERATION[char] ?? char)
        .replace(/[^a-z0-9%]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}
