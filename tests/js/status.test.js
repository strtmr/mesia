import { describe, expect, it } from 'vitest';
import { statusLine } from '../../assets/shopping/core/status.js';

const texts = {
    added: 'Hinzugefügt von %name% %when%',
    added_anonymous: 'Hinzugefügt %when%',
    checked: 'Erledigt von %name% %when%',
    checked_anonymous: 'Erledigt %when%',
    today: 'heute um %time%',
    yesterday: 'gestern um %time%',
    date: 'am %date% um %time%',
    and: 'und',
};
const names = new Map([['anna', 'Anna'], ['marco', 'Marco']]);
// Ortszeit, damit der Test in jeder Zeitzone gleich ausgeht
const now = new Date(2026, 10, 23, 14, 0);
const line = (status) => statusLine(status, { names, texts, now });

describe('Statuszeile', () => {
    it('nennt Person und Uhrzeit von heute', () => {
        expect(line({ kind: 'added', userIds: ['anna'], at: new Date(2026, 10, 23, 9, 5).toISOString() })).toBe('Hinzugefügt von Anna heute um 9:05');
    });

    it('sagt "gestern" nach Kalendertag, nicht nach 24 Stunden', () => {
        expect(line({ kind: 'checked', userIds: ['marco'], at: new Date(2026, 10, 22, 23, 50).toISOString() })).toBe('Erledigt von Marco gestern um 23:50');
    });

    it('zeigt ältere Tage als Datum, aus anderen Jahren mit Jahreszahl', () => {
        expect(line({ kind: 'added', userIds: ['anna'], at: new Date(2026, 10, 20, 7, 15).toISOString() })).toBe('Hinzugefügt von Anna am 20.11. um 7:15');
        expect(line({ kind: 'added', userIds: ['anna'], at: new Date(2025, 11, 31, 18, 0).toISOString() })).toBe('Hinzugefügt von Anna am 31.12.2025 um 18:00');
    });

    it('verbindet mehrere Personen mit "und"', () => {
        expect(line({ kind: 'added', userIds: ['anna', 'marco'], at: new Date(2026, 10, 23, 9, 5).toISOString() })).toBe('Hinzugefügt von Anna und Marco heute um 9:05');
    });

    it('lässt unbekannte Personen weg', () => {
        expect(line({ kind: 'checked', userIds: ['geloescht'], at: new Date(2026, 10, 23, 9, 5).toISOString() })).toBe('Erledigt heute um 9:05');
        expect(line({ kind: 'added', userIds: [], at: new Date(2026, 10, 23, 9, 5).toISOString() })).toBe('Hinzugefügt heute um 9:05');
    });

    it('bleibt leer ohne Zeitpunkt', () => {
        expect(line({ kind: 'added', userIds: ['anna'], at: null })).toBe('');
    });
});
