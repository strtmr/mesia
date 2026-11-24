import { describe, expect, it } from 'vitest';
import { dateStatus, extractExtras, formatValidUntil, matchStore, parseDate, searchStores, storeToken } from '../../assets/shopping/core/extras.js';
import { parseQuickAdd } from '../../assets/shopping/core/quickadd.js';

// Dienstag, 24.11.2026 (Ortszeit)
const today = new Date(2026, 10, 24, 15, 30);

describe('parseDate', () => {
    it.each([
        ['heute', '2026-11-24'],
        ['morgen', '2026-11-25'],
        ['übermorgen', '2026-11-26'],
        ['Di', '2026-11-24'],
        ['sa', '2026-11-28'],
        ['Sa.', '2026-11-28'],
        ['Samstag', '2026-11-28'],
        ['mo', '2026-11-30'],
        ['28.11.', '2026-11-28'],
        ['28.11', '2026-11-28'],
        ['5.12.2026', '2026-12-05'],
        ['5.12.27', '2027-12-05'],
        ['2.1.', '2027-01-02'],
    ])('%s → %s', (word, expected) => {
        expect(parseDate(word, today)).toBe(expected);
    });

    it.each(['31.02.', 'gestern', 'Woche', '28'])('erkennt %s nicht als Datum', (word) => {
        expect(parseDate(word, today)).toBeNull();
    });
});

describe('Schnelleingabe mit Laden und Datum', () => {
    it('löst Läden und Datum heraus, der Rest bleibt wie gewohnt', () => {
        expect(parseQuickAdd('6 Bier @Edeka bis Sa', { today })).toEqual({ name: 'Bier', quantity: 6, unit: 'stk', note: null, stores: ['Edeka'], validUntil: '2026-11-28' });
        expect(parseQuickAdd('@lidl @Rewe Milch 2l', { today })).toMatchObject({ name: 'Milch', quantity: 2, unit: 'l', stores: ['lidl', 'Rewe'] });
        expect(parseQuickAdd('Kaffee bis 28.11. (im Angebot)', { today })).toMatchObject({ name: 'Kaffee', note: 'im Angebot', validUntil: '2026-11-28' });
    });

    it('lässt "bis" ohne Datum im Namen', () => {
        expect(extractExtras('Brot bis morgen früh', today)).toEqual({ text: 'Brot früh', stores: [], validUntil: '2026-11-25' });
        expect(extractExtras('Biskuit', today).text).toBe('Biskuit');
        expect(extractExtras('Haare bis Schulter', today).text).toBe('Haare bis Schulter');
    });
});

describe('matchStore', () => {
    const stores = [{ id: 'aldi', name: 'Aldi Süd' }, { id: 'rossmann', name: 'Rossmann' }, { id: 'rewe', name: 'Rewe' }, { id: 'real', name: 'Real' }];

    it('findet exakt (ohne Leerzeichen, Umlaute, Groß-/Kleinschreibung) oder per eindeutigem Anfang', () => {
        expect(matchStore(stores, 'aldisüd')).toBe('aldi');
        expect(matchStore(stores, 'ALDI')).toBe('aldi');
        expect(matchStore(stores, 'ross')).toBe('rossmann');
        expect(matchStore(stores, 're')).toBeNull(); // Rewe oder Real
        expect(matchStore(stores, 'edeka')).toBeNull();
    });
});

describe('dateStatus und Anzeige', () => {
    it('heute, abgelaufen, später', () => {
        expect(dateStatus('2026-11-24', today)).toBe('today');
        expect(dateStatus('2026-11-23', today)).toBe('expired');
        expect(dateStatus('2026-11-25', today)).toBe('upcoming');
        expect(dateStatus(null, today)).toBeNull();
        expect(formatValidUntil('2026-11-28', today)).toBe('Sa., 28.11.');
        expect(formatValidUntil('2020-01-01', today)).toBe('1.1.2020');
        expect(formatValidUntil('2026-11-23', today, { weekday: false })).toBe('23.11.');
    });
});

describe('searchStores (tippbare Auswahl)', () => {
    const stores = ['Aldi', 'Bio Company', 'dm', 'Edeka', 'E-Center', 'Getränke Hoffmann', 'Getränkemarkt', "Hol'ab!", 'Rewe', 'Trinkgut']
        .map((name) => ({ id: name, name }));
    const names = (result) => result.map((store) => store.name);

    it('findet am Namens- oder Wortanfang zuerst, dann irgendwo im Namen', () => {
        expect(names(searchStores(stores, 'getr'))).toEqual(['Getränke Hoffmann', 'Getränkemarkt']);
        expect(names(searchStores(stores, 'comp'))).toEqual(['Bio Company']);
        expect(names(searchStores(stores, 'e'))).toEqual(['Edeka', 'E-Center', 'Getränke Hoffmann', 'Getränkemarkt', 'Rewe']);
        expect(names(searchStores(stores, 'holab'))).toEqual(["Hol'ab!"]);
    });

    it('lässt Gewählte weg und zeigt ohne Eingabe die bevorzugten', () => {
        expect(names(searchStores(stores, 'e', { exclude: ['Edeka'] }))[0]).toBe('E-Center');
        expect(names(searchStores(stores, '', { preferred: ['Rewe', 'dm'] }))).toEqual(['dm', 'Rewe']);
        expect(searchStores(stores, 'xyz')).toEqual([]);
    });

    it('schreibt Läden für die Schnelleingabe ohne Leerzeichen, matchStore findet sie wieder', () => {
        for (const store of stores) {
            expect(matchStore(stores, storeToken(store).slice(1))).toBe(store.id);
        }
        expect(storeToken({ name: 'Getränke Hoffmann' })).toBe('@GetränkeHoffmann');
    });
});

