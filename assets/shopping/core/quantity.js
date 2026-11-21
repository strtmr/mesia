/**
 * Mengen: Grundrechenarten auswerten und auf Plausibilität prüfen.
 *
 * Erlaubt sind Zahlen mit Komma oder Punkt und + - * / sowie × x : ÷ (ohne Klammern), Punkt- vor Strichrechnung:
 * "2x500" → 1000, "3+2" → 5, "1/2" → 0,5, "2*1,5" → 3.
 */

/** Höchste Menge, gleiche Grenze wie Shopping\Domain\Quantity::MAX auf dem Server. Beide gemeinsam ändern. */
export const MAX_QUANTITY = 99999;

const NUMBER = String.raw`\d+(?:[.,]\d+)?`;
const OPERATOR = String.raw`[+\-*/×xX:÷]`;

/** Eine Zahl oder ein Rechenausdruck ohne Leerzeichen, z. B. "2x500" */
export const EXPRESSION = String.raw`${NUMBER}(?:${OPERATOR}${NUMBER})*`;
const EXPRESSION_ONLY = new RegExp(`^${EXPRESSION}$`);

/**
 * Ungültige Menge. reason: "invalid" (kein Ausdruck, Division durch 0), "not_positive" (≤ 0), "too_large".
 */
export class QuantityError extends Error {
    constructor(reason, value) {
        super(`Ungültige Menge (${reason}): ${value}`);
        this.name = 'QuantityError';
        this.reason = reason;
        this.value = value;
    }
}

/**
 * Wertet einen Ausdruck aus, ohne die Grenzen zu prüfen (für die Schnelleingabe, die auch beim Tippen läuft).
 *
 * @returns {number} Ergebnis, NaN/Infinity bei Division durch 0
 */
export function evaluateExpression(expression) {
    const parts = expression.match(new RegExp(`${NUMBER}|${OPERATOR}`, 'g'));
    let sum = 0;
    let sign = 1;
    let product = toNumber(parts[0]);

    for (let i = 1; i < parts.length; i += 2) {
        const operator = parts[i];
        const operand = toNumber(parts[i + 1]);
        if (operator === '+' || operator === '-') {
            sum += sign * product;
            sign = operator === '+' ? 1 : -1;
            product = operand;
        } else if (operator === '/' || operator === ':' || operator === '÷') {
            product /= operand;
        } else {
            product *= operand;
        }
    }

    return sum + sign * product;
}

/**
 * Prüft eine Menge und rundet auf 3 Nachkommastellen (wie der Server speichert).
 *
 * @throws {QuantityError}
 */
export function validateQuantity(value) {
    if (!Number.isFinite(value)) {
        throw new QuantityError('invalid', value);
    }
    const rounded = Math.round(value * 1000) / 1000;
    if (rounded <= 0) {
        throw new QuantityError('not_positive', value);
    }
    if (rounded > MAX_QUANTITY) {
        throw new QuantityError('too_large', value);
    }

    return rounded;
}

/**
 * Mengenfeld im Bearbeiten-Sheet: leer → null, sonst Zahl oder Rechenausdruck.
 *
 * @throws {QuantityError}
 */
export function parseQuantity(text) {
    const compact = String(text ?? '').replace(/\s+/g, '');
    if (compact === '') {
        return null;
    }
    if (!EXPRESSION_ONLY.test(compact)) {
        throw new QuantityError('invalid', text);
    }

    return validateQuantity(evaluateExpression(compact));
}

function toNumber(value) {
    return Number.parseFloat(value.replace(',', '.'));
}
