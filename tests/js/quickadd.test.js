import { describe, expect, it } from 'vitest';
import { parseQuickAdd, textLength } from '../../assets/shopping/core/quickadd.js';

describe('parseQuickAdd', () => {
    it.each([
        ['Milch', { name: 'Milch', quantity: null, unit: null, note: null }],
        ['2 l Milch', { name: 'Milch', quantity: 2, unit: 'l', note: null }],
        ['2l Milch', { name: 'Milch', quantity: 2, unit: 'l', note: null }],
        ['Milch 2l', { name: 'Milch', quantity: 2, unit: 'l', note: null }],
        ['Milch, 2 Liter', { name: 'Milch', quantity: 2, unit: 'l', note: null }],
        ['3 Zwiebeln', { name: 'Zwiebeln', quantity: 3, unit: 'stk', note: null }],
        ['500g Hack', { name: 'Hack', quantity: 500, unit: 'g', note: null }],
        ['1,5 kg Kartoffeln', { name: 'Kartoffeln', quantity: 1.5, unit: 'kg', note: null }],
        ['2 Dosen Tomaten', { name: 'Tomaten', quantity: 2, unit: 'dose', note: null }],
        ['2 Stück Brötchen', { name: 'Brötchen', quantity: 2, unit: 'stk', note: null }],
        ['Butter (die gute)', { name: 'Butter', quantity: null, unit: null, note: 'die gute' }],
        ['Tomaten, gehackt', { name: 'Tomaten, gehackt', quantity: null, unit: null, note: null }],
        ['Milch 1,5 %', { name: 'Milch 1,5 %', quantity: null, unit: null, note: null }],
        ['7up', { name: '7up', quantity: null, unit: null, note: null }],
        // Grundrechenarten
        ['2x500g Mehl', { name: 'Mehl', quantity: 1000, unit: 'g', note: null }],
        ['2 x 500 g Hack', { name: 'Hack', quantity: 1000, unit: 'g', note: null }],
        ['3+2 Äpfel', { name: 'Äpfel', quantity: 5, unit: 'stk', note: null }],
        ['1/2 Zitrone', { name: 'Zitrone', quantity: 0.5, unit: 'stk', note: null }],
        ['Milch 2*1,5l', { name: 'Milch', quantity: 3, unit: 'l', note: null }],
        ['3x Brötchen', { name: 'Brötchen', quantity: 3, unit: 'stk', note: null }],
        // Unplausibel: wird hier nur erkannt, addArticle() lehnt ab
        ['1111111111 Schokolade', { name: 'Schokolade', quantity: 1111111111, unit: 'stk', note: null }],
        ['  ', { name: '', quantity: null, unit: null, note: null }],
    ])('%s', (input, expected) => {
        expect(parseQuickAdd(input)).toEqual({ stores: [], validUntil: null, ...expected });
    });
});

describe('textLength', () => {
    it('zählt Zeichen wie PHP mb_strlen (Emoji = 1 Zeichen)', () => {
        expect(textLength('Äpfel')).toBe(5);
        expect(textLength('🍎🍐')).toBe(2);
        expect(textLength(null)).toBe(0);
    });
});
