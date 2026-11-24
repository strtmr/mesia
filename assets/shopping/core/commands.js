import { NAME_MAX_LENGTH, NOTE_MAX_LENGTH, parseQuickAdd, textLength } from './quickadd.js';
import { validateQuantity } from './quantity.js';
import { matchArticle } from './matcher.js';
import { activeList, activeStores, fallbackCategory, findItemByArticle } from './state.js';
import { matchStore } from './extras.js';
import { compareKeys, keyBetween } from './sortkey.js';

/**
 * Fachliche Aktionen → Mutationen für die Outbox. Reine Funktionen: Sie lesen den Zustand, ändern ihn aber nicht.
 *
 * @typedef {{newId: () => string, now: () => string}} Context
 */

/** Standard-Kontext: zufällige UUIDs, Zeit sekundengenau (wie der Server, siehe docs/entwicklung.md). */
export function createContext() {
    return {
        newId: () => crypto.randomUUID(),
        now: () => new Date(Math.floor(Date.now() / 1000) * 1000).toISOString(),
    };
}

/**
 * Schnelleingabe übernehmen: "2 l Milch". Unbekannte Begriffe werden als neuer Artikel in "Sonstiges" angelegt.
 *
 * @returns {Array<object>} Mutationen (leer, wenn nichts Sinnvolles eingegeben wurde)
 */
/**
 * Text länger als erlaubt (field: "name" oder "note"). Die Eingabe zeigt das schon beim Tippen an,
 * das hier verhindert nur, dass Unzulässiges in die Outbox gelangt.
 */
export class TextTooLongError extends Error {
    constructor(field, length, max) {
        super(`${field} zu lang: ${length} von höchstens ${max} Zeichen`);
        this.name = 'TextTooLongError';
        this.field = field;
        this.length = length;
        this.max = max;
    }
}

/** "@xyz" passt zu keinem Laden (oder zu mehreren). Dann wird nichts angelegt, die Eingabe bleibt stehen. */
export class UnknownStoreError extends Error {
    constructor(token) {
        super(`Unbekannter Laden: ${token}`);
        this.name = 'UnknownStoreError';
        this.token = token;
    }
}

export function addFromText(state, text, ctx) {
    const parsed = parseQuickAdd(text);
    if (!parsed.name) {
        return [];
    }

    return addArticle(state, matchArticle(state, parsed.name), parsed, ctx);
}

/**
 * @param {object|null} article bekannter Artikel oder null (dann wird er angelegt)
 * @param {{name: string, quantity: number|null, unit: string|null, note: string|null}} parsed
 * @throws {QuantityError} unplausible Menge (≤ 0, zu groß, Division durch 0), dann wird nichts angelegt
 */
export function addArticle(state, article, parsed, ctx) {
    const list = activeList(state);
    if (!list) {
        throw new Error('Keine Einkaufsliste vorhanden. Bitte zuerst synchronisieren.');
    }
    const quantity = parsed.quantity === null || parsed.quantity === undefined ? null : validateQuantity(parsed.quantity);
    if (!article && textLength(parsed.name) > NAME_MAX_LENGTH) {
        throw new TextTooLongError('name', textLength(parsed.name), NAME_MAX_LENGTH);
    }
    if (textLength(parsed.note) > NOTE_MAX_LENGTH) {
        throw new TextTooLongError('note', textLength(parsed.note), NOTE_MAX_LENGTH);
    }
    const stores = activeStores(state);
    const storeIds = (parsed.stores ?? []).map((token) => matchStore(stores, token) ?? (() => { throw new UnknownStoreError(token); })());

    const mutations = [];
    let target = article;

    if (!target) {
        const category = fallbackCategory(state);
        const name = capitalize(parsed.name);
        target = { id: ctx.newId(), name, category_id: category.id };
        mutations.push(mutation('article.create', { article_id: target.id, name, category_id: category.id }, ctx));
    }

    const existing = findItemByArticle(state, list.id, target.id);
    const categoryId = existing?.category_id ?? target.category_id;

    mutations.push(mutation('item.add', {
        item_id: ctx.newId(),
        list_id: list.id,
        article_id: target.id,
        name: target.name,
        category_id: categoryId,
        sort_key: existing?.sort_key ?? keyBetween(lastSortKey(state, list.id, categoryId), null),
        note: parsed.note ?? null,
        contribution: { id: ctx.newId(), quantity, unit: quantity === null ? null : (parsed.unit ?? null) },
        ...(storeIds.length > 0 ? { store_ids: [...new Set(storeIds)] } : {}),
        ...(parsed.validUntil ? { valid_until: parsed.validUntil } : {}),
    }, ctx));

    return mutations;
}

export function checkItem(itemId, ctx) {
    return [mutation('item.check', { item_id: itemId }, ctx)];
}

export function uncheckItem(itemId, ctx) {
    return [mutation('item.uncheck', { item_id: itemId }, ctx)];
}

export function reactivateItem(itemId, ctx) {
    return [mutation('item.reactivate', { item_id: itemId, contribution: { id: ctx.newId(), quantity: null, unit: null } }, ctx)];
}

export function deleteItem(itemId, ctx) {
    return [mutation('item.delete', { item_id: itemId }, ctx)];
}

/**
 * Änderungen aus dem Bearbeiten-Sheet. Nur geänderte Felder werden gesendet.
 *
 * @param {object} item Eintrag aus dem Zustand
 * @param {{name: string, note: string, quantity: number|null, unit: string|null, categoryId: string, storeIds?: string[], validUntil?: string|null}} form
 * @param {{quantity: number|null, unit: string|null}} currentQuantity
 */
export function editItem(state, item, form, currentQuantity, ctx) {
    const mutations = [];
    const fields = {};

    const name = form.name.trim();
    if (name && name !== item.name) fields.name = name;

    const note = form.note.trim() || null;
    if (note !== (item.note ?? null)) fields.note = note;

    if (form.quantity !== currentQuantity.quantity || (form.quantity !== null && form.unit !== currentQuantity.unit)) {
        fields.qty_override = form.quantity;
        fields.unit_override = form.quantity === null ? null : form.unit;
    }

    if (form.storeIds !== undefined) {
        const before = [...(item.store_ids ?? [])].sort();
        const after = [...new Set(form.storeIds)].sort();
        if (before.join() !== after.join()) fields.store_ids = after;
    }
    if (form.validUntil !== undefined && (form.validUntil || null) !== (item.valid_until ?? null)) {
        fields.valid_until = form.validUntil || null;
    }

    if (Object.keys(fields).length > 0) {
        mutations.push(mutation('item.update', { item_id: item.id, fields }, ctx));
    }

    if (form.categoryId && form.categoryId !== item.category_id) {
        mutations.push(...moveItem(state, item.id, form.categoryId, null, null, ctx));
    }

    return mutations;
}

/**
 * Verschieben per Drag & Drop. Ohne Nachbarn: ans Ende der Kategorie.
 */
export function moveItem(state, itemId, categoryId, previousId, nextId, ctx) {
    const item = state.items.get(itemId);
    if (!item) {
        return [];
    }

    const previous = previousId ? state.items.get(previousId)?.sort_key ?? null : null;
    const next = nextId ? state.items.get(nextId)?.sort_key ?? null : null;
    const sortKey = previous === null && next === null
        ? keyBetween(lastSortKey(state, item.list_id, categoryId, itemId), null)
        : keyBetween(previous, next);

    return [mutation('item.move', { item_id: itemId, category_id: categoryId, sort_key: sortKey }, ctx)];
}

function lastSortKey(state, listId, categoryId, exceptItemId = null) {
    let last = null;
    for (const item of state.items.values()) {
        if (!item.deleted && !item.checked_at && item.list_id === listId && item.category_id === categoryId && item.id !== exceptItemId) {
            if (last === null || compareKeys(item.sort_key, last) > 0) {
                last = item.sort_key;
            }
        }
    }

    return last;
}

function mutation(type, payload, ctx) {
    return { id: ctx.newId(), type, at: ctx.now(), payload };
}

function capitalize(text) {
    const trimmed = text.trim();

    return trimmed.charAt(0).toLocaleUpperCase('de-DE') + trimmed.slice(1);
}
