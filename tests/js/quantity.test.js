import { describe, expect, it } from 'vitest';
import { MAX_QUANTITY, QuantityError, evaluateExpression, parseQuantity, validateQuantity } from '../../assets/shopping/core/quantity.js';
import { formatInputNumber, formatNumber } from '../../assets/shopping/core/view.js';

describe('Rechenausdrücke', () => {
    it.each([
        ['2x500', 1000],
        ['2×500', 1000],
        ['2*1,5', 3],
        ['3+2', 5],
        ['10-3', 7],
        ['1/2', 0.5],
        ['1:4', 0.25],
        ['2+3*4', 14], // Punkt vor Strich
        ['10-2*3', 4],
        ['0,5+0.25', 0.75],
    ])('%s = %s', (expression, expected) => {
        expect(evaluateExpression(expression)).toBeCloseTo(expected, 10);
    });
});

describe('Plausibilität', () => {
    it('rundet auf 3 Nachkommastellen wie der Server', () => {
        expect(validateQuantity(1 / 3)).toBe(0.333);
    });

    it.each([
        [1111111111, 'too_large'],
        [MAX_QUANTITY + 1, 'too_large'],
        [0, 'not_positive'],
        [-1, 'not_positive'],
        [0.0001, 'not_positive'],
        [Infinity, 'invalid'],
        [Number.NaN, 'invalid'],
    ])('%s → %s', (value, reason) => {
        expect(() => validateQuantity(value)).toThrow(QuantityError);
        try {
            validateQuantity(value);
        } catch (error) {
            expect(error.reason).toBe(reason);
        }
    });

    it('erlaubt die Obergrenze', () => {
        expect(validateQuantity(MAX_QUANTITY)).toBe(MAX_QUANTITY);
    });
});

describe('Mengenfeld im Bearbeiten-Sheet', () => {
    it.each([
        ['', null],
        ['  ', null],
        ['1,5', 1.5],
        ['2 x 500', 1000],
        ['1000', 1000],
    ])('"%s" → %s', (input, expected) => {
        expect(parseQuantity(input)).toBe(expected);
    });

    it.each(['abc', '2x', '1/0', '0'])('"%s" ist ungültig', (input) => {
        expect(() => parseQuantity(input)).toThrow(QuantityError);
    });

    it('zeigt Mengen ohne Tausenderpunkt an, damit "1.000" beim Speichern nicht zu 1 wird', () => {
        expect(formatNumber(1000)).toBe('1.000');
        expect(formatInputNumber(1000)).toBe('1000');
        expect(parseQuantity(formatInputNumber(1000))).toBe(1000);
        expect(formatInputNumber(1.5)).toBe('1,5');
    });
});
