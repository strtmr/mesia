import { unitFromToken } from './units.js';

const NUMBER = /^\d+(?:[.,]\d+)?$/;
const NUMBER_WITH_UNIT = /^(\d+(?:[.,]\d+)?)([a-zA-ZäöüÄÖÜß]+\.?)$/;

/**
 * Zerlegt die Schnelleingabe: "2 l Milch", "2l Milch", "Milch 2l", "Milch, 2 Liter", "3 Zwiebeln",
 * "Butter (die gute)".
 *
 * Ohne Einheit, aber mit Zahl gilt "Stück".
 *
 * @param {string} input
 * @returns {{name: string, quantity: number|null, unit: string|null, note: string|null}}
 */
export function parseQuickAdd(input) {
    let text = input.trim().replace(/\s+/g, ' ');
    let note = null;

    const noteMatch = text.match(/\(([^)]*)\)\s*$/);
    if (noteMatch) {
        note = noteMatch[1].trim() || null;
        text = text.slice(0, noteMatch.index).trim();
    }

    const tokens = text.replace(/(?<!\d),|,(?!\d)/g, ' , ').split(' ').filter((token) => token !== '');

    const leading = readQuantity(tokens, 0);
    if (leading && leading.consumed < tokens.length) {
        return result(tokens.slice(leading.consumed), leading, note);
    }

    const trailing = readTrailingQuantity(tokens);
    if (trailing) {
        return result(tokens.slice(0, tokens.length - trailing.consumed), trailing, note);
    }

    return result(tokens, null, note);
}

function readQuantity(tokens, start) {
    const first = tokens[start];
    if (first === undefined) {
        return null;
    }

    const glued = first.match(NUMBER_WITH_UNIT);
    if (glued && unitFromToken(glued[2])) {
        return { quantity: toNumber(glued[1]), unit: unitFromToken(glued[2]), consumed: start + 1 };
    }

    if (!NUMBER.test(first)) {
        return null;
    }

    const unit = tokens[start + 1] !== undefined ? unitFromToken(tokens[start + 1]) : null;

    return { quantity: toNumber(first), unit, consumed: start + (unit ? 2 : 1) };
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

function toNumber(value) {
    return Number.parseFloat(value.replace(',', '.'));
}
