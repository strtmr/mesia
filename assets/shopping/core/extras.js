import { normalize } from './normalize.js';

/**
 * Läden ("@Edeka") und "gültig bis" ("bis Sa") in der Schnelleingabe, dazu die Anzeige des Datums.
 */

const WEEKDAYS = [
    ['so', 'sonntag'], ['mo', 'montag'], ['di', 'dienstag'], ['mi', 'mittwoch'],
    ['do', 'donnerstag'], ['fr', 'freitag'], ['sa', 'samstag'],
];
const RELATIVE = { heute: 0, morgen: 1, 'übermorgen': 2, uebermorgen: 2 };
const STORE_TOKEN = /(?:^|\s)@([\p{L}\p{N}&'._-]+)/gu;

/**
 * Nimmt "@Laden" und "bis <Datum>" aus dem Text.
 *
 * @param {string} text
 * @param {Date} today
 * @returns {{text: string, stores: string[], validUntil: string|null}}
 */
export function extractExtras(text, today = new Date()) {
    const stores = [];
    let rest = text.replace(STORE_TOKEN, (match, token) => {
        stores.push(token.replace(/[.,]+$/, ''));
        return ' ';
    });

    let validUntil = null;
    rest = rest.replace(/(?:^|\s|,)bis\s+(\S+)(?=\s|$)/giu, (match, word) => {
        const date = parseDate(word, today);
        if (date === null) {
            return match;
        }
        validUntil = date;
        return ' ';
    });

    return { text: rest.replace(/\s+/g, ' ').trim(), stores, validUntil };
}

/**
 * "heute", "morgen", "Sa", "Samstag", "28.11.", "28.11.2026" → "2026-11-28". Wochentage: der nächste (auch heute).
 * Ein Datum ohne Jahr, das schon vorbei ist, gilt fürs nächste Jahr.
 *
 * @returns {string|null}
 */
export function parseDate(word, today = new Date()) {
    const value = word.toLocaleLowerCase('de').replace(/[.,]+$/, (dots) => (/^\d/.test(word) ? dots : ''));
    const start = startOfDay(today);

    if (value in RELATIVE) {
        return isoDate(addDays(start, RELATIVE[value]));
    }
    const weekday = WEEKDAYS.findIndex((names) => names.includes(value.replace(/\.$/, '')));
    if (weekday >= 0) {
        return isoDate(addDays(start, (weekday - start.getDay() + 7) % 7));
    }

    const match = value.match(/^(\d{1,2})\.(\d{1,2})\.?(\d{2}|\d{4})?$/);
    if (!match) {
        return null;
    }
    const day = Number(match[1]);
    const month = Number(match[2]) - 1;
    let year = match[3] ? Number(match[3].length === 2 ? `20${match[3]}` : match[3]) : start.getFullYear();
    let date = new Date(year, month, day);
    if (date.getMonth() !== month || date.getDate() !== day) {
        return null; // 31.02.
    }
    if (!match[3] && date < start) {
        year += 1;
        date = new Date(year, month, day);
    }

    return isoDate(date);
}

/**
 * "@edeka" → Laden-ID. Erst exakt (ohne Leerzeichen, wie normalize), sonst eindeutiger Anfang ("@ros" → Rossmann).
 *
 * @param {Array<{id: string, name: string}>} stores aktive Läden
 * @returns {string|null}
 */
export function matchStore(stores, token) {
    const wanted = compact(token);
    if (wanted === '') {
        return null;
    }
    const exact = stores.find((store) => compact(store.name) === wanted);
    if (exact) {
        return exact.id;
    }
    const prefixed = stores.filter((store) => compact(store.name).startsWith(wanted));

    return prefixed.length === 1 ? prefixed[0].id : null;
}

/**
 * Läden zum Antippen, während man tippt: erst Treffer am Namens- oder Wortanfang, dann irgendwo im Namen, jeweils
 * alphabetisch. Ohne Eingabe die bevorzugten (z. B. auf der Liste schon verwendeten) Läden.
 *
 * @param {Array<{id: string, name: string}>} stores aktive Läden, alphabetisch
 * @param {{exclude?: string[], preferred?: string[], limit?: number}} options
 */
export function searchStores(stores, query, { exclude = [], preferred = [], limit = 6 } = {}) {
    const candidates = stores.filter((store) => !exclude.includes(store.id));
    const wanted = compact(query);
    if (wanted === '') {
        return candidates.filter((store) => preferred.includes(store.id)).slice(0, limit);
    }

    const rank = (store) => {
        const words = normalize(store.name).split(' ');
        if (compact(store.name).startsWith(wanted) || words.some((word) => word.startsWith(wanted))) {
            return 0;
        }

        return compact(store.name).includes(wanted) ? 1 : null;
    };

    return candidates
        .map((store) => ({ store, rank: rank(store) }))
        .filter((entry) => entry.rank !== null)
        .sort((a, b) => a.rank - b.rank)
        .slice(0, limit)
        .map((entry) => entry.store);
}

/** Wie ein Laden in der Schnelleingabe geschrieben wird: "@GetränkeHoffmann" (ohne Leerzeichen, matchStore findet ihn) */
export function storeToken(store) {
    return `@${store.name.replace(/[^\p{L}\p{N}]+/gu, '')}`;
}

/**
 * Für die Liste: heute (grün), abgelaufen (rot), später (normal).
 *
 * @returns {'today'|'expired'|'upcoming'|null}
 */
export function dateStatus(validUntil, today = new Date()) {
    if (!validUntil) {
        return null;
    }
    const todayIso = isoDate(startOfDay(today));
    if (validUntil === todayIso) {
        return 'today';
    }

    return validUntil < todayIso ? 'expired' : 'upcoming';
}

const dateFormat = new Intl.DateTimeFormat('de-DE', { weekday: 'short', day: 'numeric', month: 'numeric' });
const dateWithYearFormat = new Intl.DateTimeFormat('de-DE', { day: 'numeric', month: 'numeric', year: 'numeric' });

const dateShortFormat = new Intl.DateTimeFormat('de-DE', { day: 'numeric', month: 'numeric' });

/**
 * "2026-11-28" → "Sa., 28.11." (weekday: false → "28.11."), aus einem anderen Jahr "1.1.2020"
 */
export function formatValidUntil(validUntil, today = new Date(), { weekday = true } = {}) {
    const [year, month, day] = validUntil.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    if (year !== today.getFullYear()) {
        return dateWithYearFormat.format(date);
    }

    return (weekday ? dateFormat : dateShortFormat).format(date);
}

export function isoDate(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

function addDays(date, days) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
}

function compact(text) {
    return normalize(text).replace(/ /g, '');
}
