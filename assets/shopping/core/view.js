import { activeList, fallbackCategory, time } from './state.js';
import { compareKeys } from './sortkey.js';
import { unitLabel } from './units.js';

const numberFormat = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 3 });

/**
 * Bereitet den Zustand für die Anzeige auf: offene Einträge nach Kategorien gruppiert, Erledigtes separat.
 *
 * keepInPlace: gerade abgehakte Einträge, die noch kurz an ihrem Platz bleiben (abgehakt dargestellt), damit die
 * Zeilen darunter beim schnellen Abhaken nicht unter dem Finger wegrutschen.
 */
export function buildListView(state, { doneLimit = 100, keepInPlace = new Set() } = {}) {
    const list = activeList(state);
    const categories = [...state.categories.values()]
        .filter((category) => !category.deleted)
        .sort((a, b) => compareKeys(a.sort_key, b.sort_key));
    const fallback = fallbackCategory(state);
    const knownCategories = new Set(categories.map((category) => category.id));

    const open = new Map(categories.map((category) => [category.id, []]));
    const done = [];

    for (const item of state.items.values()) {
        if (item.deleted || !list || item.list_id !== list.id) {
            continue;
        }
        if (item.checked_at && !keepInPlace.has(item.id)) {
            done.push(item);
            continue;
        }
        const categoryId = knownCategories.has(item.category_id) ? item.category_id : fallback?.id;
        open.get(categoryId)?.push(item);
    }

    const byPosition = (a, b) => compareKeys(a.sort_key, b.sort_key) || a.name.localeCompare(b.name, 'de');

    const groups = categories
        .map((category) => ({ category, items: open.get(category.id).sort(byPosition).map(toViewItem) }))
        .filter((group) => group.items.length > 0);

    return {
        listId: list?.id ?? null,
        categories,
        groups,
        openCount: groups.reduce((sum, group) => sum + group.items.length, 0),
        done: done.sort(byFrequency(state)).slice(0, doneLimit).map(toViewItem),
    };
}

/**
 * „Erledigt“: oft Gekauftes zuerst (Kaufzähler am Artikel), bei Gleichstand das zuletzt Gekaufte.
 * Erledigt dient vor allem zum schnellen Wiederaktivieren.
 */
function byFrequency(state) {
    const purchases = (item) => state.articles.get(item.article_id)?.purchase_count ?? 0;

    return (a, b) => purchases(b) - purchases(a) || time(b.checked_at) - time(a.checked_at);
}

function toViewItem(item) {
    return {
        id: item.id,
        name: item.name,
        note: item.note,
        categoryId: item.category_id,
        quantityLabel: formatQuantity(item),
        editableQuantity: editableQuantity(item),
        checked: Boolean(item.checked_at),
        pending: Boolean(item.pending),
        status: itemStatus(item),
    };
}

/**
 * Wer hat den Eintrag auf die Liste gesetzt und wer abgehakt, und wann? Für den Bearbeiten-Dialog, Texte macht
 * status.js daraus. Hinzugefügt: alle, die zur aktuellen Runde beigetragen haben (nach dem Reaktivieren zählen nur
 * neue Beiträge), Zeitpunkt des ersten Beitrags.
 *
 * @returns {{added: {kind: 'added', userIds: string[], at: string|null}, checked: {kind: 'checked', userIds: string[], at: string}|null}}
 */
export function itemStatus(item) {
    const contributions = [...(item.contributions ?? [])].sort((a, b) => time(a.created_at) - time(b.created_at));
    const added = {
        kind: 'added',
        userIds: [...new Set(contributions.map((c) => c.created_by).filter(Boolean))],
        at: contributions[0]?.created_at ?? item.activated_at ?? null,
    };
    const checked = item.checked_at ? { kind: 'checked', userIds: item.checked_by ? [item.checked_by] : [], at: item.checked_at } : null;

    return { added, checked };
}

/**
 * "2 l", "500 g + 1 Pck" oder "" (keine Menge).
 */
export function formatQuantity(item) {
    if (item.qty_override !== null && item.qty_override !== undefined) {
        return format(item.qty_override, item.unit_override);
    }

    const sums = new Map();
    for (const contribution of item.contributions ?? []) {
        if (contribution.quantity !== null && contribution.quantity !== undefined) {
            const unit = contribution.unit ?? '';
            sums.set(unit, (sums.get(unit) ?? 0) + Number(contribution.quantity));
        }
    }

    return [...sums.entries()].map(([unit, quantity]) => format(quantity, unit || null)).join(' + ');
}

/**
 * Menge fürs Bearbeiten: manuelle Menge oder die Summe, wenn alle Beiträge dieselbe Einheit haben.
 *
 * @returns {{quantity: number|null, unit: string|null}}
 */
export function editableQuantity(item) {
    if (item.qty_override !== null && item.qty_override !== undefined) {
        return { quantity: Number(item.qty_override), unit: item.unit_override ?? null };
    }
    const withQuantity = (item.contributions ?? []).filter((c) => c.quantity !== null && c.quantity !== undefined);
    const units = new Set(withQuantity.map((c) => c.unit ?? null));
    if (withQuantity.length === 0 || units.size !== 1) {
        return { quantity: null, unit: null };
    }

    return { quantity: withQuantity.reduce((sum, c) => sum + Number(c.quantity), 0), unit: [...units][0] };
}

export function formatNumber(value) {
    return numberFormat.format(value);
}

const inputNumberFormat = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 3, useGrouping: false });

/**
 * Für Eingabefelder: ohne Tausenderpunkt ("1000", "1,5"). Sonst würde "1.000" beim Speichern als 1 gelesen.
 */
export function formatInputNumber(value) {
    return inputNumberFormat.format(value);
}

function format(quantity, unit) {
    const label = unitLabel(unit);

    return label ? `${formatNumber(Number(quantity))} ${label}` : formatNumber(Number(quantity));
}
