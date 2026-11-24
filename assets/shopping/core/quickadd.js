import { unitFromToken } from './units.js';
import { EXPRESSION, evaluateExpression } from './quantity.js';
import { extractExtras } from './extras.js';

// Menge = Zahl oder Rechenausdruck ("2x500"), optional direkt mit Einheit ("2x500g")
const NUMBER = new RegExp(`^(${EXPRESSION})$`);
const NUMBER_WITH_UNIT = new RegExp(`^(${EXPRESSION})([a-zA-ZäöüÄÖÜß]+\\.?)$`);
/** Gleiche Grenzen wie auf dem Server (Spaltenlängen von Artikel und Eintrag) */
export const NAME_MAX_LENGTH = 100;
export const NOTE_MAX_LENGTH = 200;
/** Ab hier zeigt die Eingabe an, wie viele Zeichen noch gehen */
export const LENGTH_WARNING_AT = 90;

/** Länge in Zeichen wie PHP mb_strlen (Emojis zählen als ein Zeichen) */
export function textLength(text) {
    return [...(text ?? '')].length;
}

/** "3x Brötchen": x direkt hinter der Zahl bedeutet Stück */
const TIMES = ['x', 'X'];

/**
 * Zerlegt die Schnelleingabe: "2 l Milch", "2l Milch", "Milch 2l", "Milch, 2 Liter", "3 Zwiebeln",
 * "Butter (die gute)", "2x500g Mehl", "3+2 Äpfel", "3x Brötchen", "Bier @Edeka bis Sa".
 *
 * Ohne Einheit, aber mit Zahl gilt "Stück". Die Menge wird hier nicht auf Grenzen geprüft (läuft auch beim Tippen),
 * das macht addArticle() mit validateQuantity().
 *
 * @param {string} input
 * @param {{today?: Date}} options today: Bezug für "bis morgen", "bis Sa"
 * @returns {{name: string, quantity: number|null, unit: string|null, note: string|null, stores: string[], validUntil: string|null}}
 *          stores: Laden-Namen, wie getippt (ohne "@"), validUntil: "YYYY-MM-DD"
 */
export function parseQuickAdd(input, { today = new Date() } = {}) {
    const extras = extractExtras(input.trim().replace(/\s+/g, ' '), today);
    let text = extras.text
        // Leerzeichen um Rechenzeichen zwischen Zahlen entfernen: "2 x 500 g" → "2x500 g", "3 + 2" → "3+2"
        .replace(/(\d) ?([+*/×:÷]) ?(?=\d)/g, '$1$2')
        .replace(/(\d) ([xX-]) (?=\d)/g, '$1$2');
    let note = null;

    const noteMatch = text.match(/\(([^)]*)\)\s*$/);
    if (noteMatch) {
        note = noteMatch[1].trim() || null;
        text = text.slice(0, noteMatch.index).trim();
    }

    const tokens = text.replace(/(?<!\d),|,(?!\d)/g, ' , ').split(' ').filter((token) => token !== '');

    const leading = readQuantity(tokens, 0);
    if (leading && leading.consumed < tokens.length) {
        return { ...result(tokens.slice(leading.consumed), leading, note), stores: extras.stores, validUntil: extras.validUntil };
    }

    const trailing = readTrailingQuantity(tokens);
    if (trailing) {
        return { ...result(tokens.slice(0, tokens.length - trailing.consumed), trailing, note), stores: extras.stores, validUntil: extras.validUntil };
    }

    return { ...result(tokens, null, note), stores: extras.stores, validUntil: extras.validUntil };
}

function readQuantity(tokens, start) {
    const first = tokens[start];
    if (first === undefined) {
        return null;
    }

    const glued = first.match(NUMBER_WITH_UNIT);
    if (glued && unitFromToken(glued[2])) {
        return { quantity: evaluateExpression(glued[1]), unit: unitFromToken(glued[2]), consumed: start + 1 };
    }
    if (glued && TIMES.includes(glued[2])) {
        return { quantity: evaluateExpression(glued[1]), unit: null, consumed: start + 1 };
    }

    if (!NUMBER.test(first)) {
        return null;
    }

    const unit = tokens[start + 1] !== undefined ? unitFromToken(tokens[start + 1]) : null;

    return { quantity: evaluateExpression(first), unit, consumed: start + (unit ? 2 : 1) };
}

function readTrailingQuantity(tokens) {
    // "… 2l" oder "… 2 Liter" (optional mit Komma davor)
    for (const length of [2, 1]) {
        const start = tokens.length - length;
        if (start < 1) {
            continue;
        }
        const quantity = readQuantity(tokens, start);
        if (quantity && quantity.consumed === tokens.length) {
            const commaBefore = tokens[start - 1] === ',' ? 1 : 0;
            return { ...quantity, consumed: length + commaBefore };
        }
    }

    return null;
}

function result(nameTokens, quantity, note) {
    const name = nameTokens
        .join(' ')
        .replace(/\s+,\s*/g, ', ')
        .replace(/^[,\s]+|[,\s]+$/g, '');

    return {
        name,
        quantity: quantity?.quantity ?? null,
        unit: quantity ? (quantity.unit ?? 'stk') : null,
        note,
    };
}
