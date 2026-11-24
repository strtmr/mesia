import { normalize } from './normalize.js';

/**
 * Lokaler Zustand = bestätigter Serverzustand + ausstehende Mutationen (Outbox), im Speicher berechnet.
 *
 * Die Regeln spiegeln die Server-Handler (src/Shopping/Application/Mutation, src/Catalog/Application/Mutation),
 * damit die Oberfläche sofort zeigt, was der Server später bestätigt. Weicht etwas ab, gewinnt nach dem nächsten
 * Sync automatisch der Server, denn bestätigte Mutationen verlassen die Outbox.
 */

const COLLECTIONS = {
    category: 'categories',
    article: 'articles',
    article_alias: 'aliases',
    shopping_list: 'lists',
    shopping_item: 'items',
    store: 'stores',
};

/**
 * @param {Iterable<{type: string, data: object}>} serverEntities
 * @param {Array<{type: string, at: string, payload: object}>} outbox
 * @param {{actorId?: string|null}} options actorId: angemeldeter Benutzer, für „Hinzugefügt von“/„Erledigt von“ an
 *        lokalen Änderungen (der Server setzt es selbst)
 */
export function buildState(serverEntities, outbox = [], { actorId = null } = {}) {
    const state = {
        categories: new Map(),
        articles: new Map(),
        aliases: new Map(),
        lists: new Map(),
        items: new Map(),
        stores: new Map(),
    };

    for (const { type, data } of serverEntities) {
        const collection = COLLECTIONS[type];
        if (collection) {
            state[collection].set(data.id, data);
        }
    }

    for (const mutation of outbox) {
        applyMutation(state, mutation, actorId);
    }

    return state;
}

/**
 * Wendet eine Mutation an. Objekte werden vor dem Ändern kopiert, der Serverzustand bleibt unberührt.
 */
export function applyMutation(state, mutation, actorId = null) {
    const handler = HANDLERS[mutation.type];
    if (handler) {
        handler(state, mutation.payload, mutation.at, actorId);
    }
}

const HANDLERS = {
    'item.add'(state, p, at, actorId) {
        if (state.items.has(p.item_id)) {
            return;
        }
        if (p.article_id) {
            const existing = findItemByArticle(state, p.list_id, p.article_id);
            if (existing) {
                addContribution(state, existing.id, p.contribution, at, actorId);
                addExtras(state, existing.id, p);
                return;
            }
        }
        state.items.set(p.item_id, {
            id: p.item_id,
            list_id: p.list_id,
            article_id: p.article_id ?? null,
            name: p.name.trim(),
            category_id: p.category_id,
            note: p.note ?? null,
            qty_override: null,
            unit_override: null,
            sort_key: p.sort_key,
            checked_at: null,
            checked_by: null,
            activated_at: at,
            store_ids: [...new Set(p.store_ids ?? [])],
            valid_until: p.valid_until ?? null,
            contributions: [contribution(p.contribution, at, actorId)],
            deleted: false,
            pending: true,
        });
    },

    'item.reactivate'(state, p, at, actorId) {
        addContribution(state, p.item_id, p.contribution, at, actorId);
    },

    'item.check'(state, p, at, actorId) {
        const item = state.items.get(p.item_id);
        if (!item || item.deleted || item.checked_at || time(at) < time(item.activated_at)) {
            return;
        }
        const changed = edit(state.items, p.item_id);
        changed.checked_at = at;
        changed.checked_by = actorId;
        countPurchase(state, item.article_id, +1);
    },

    'item.uncheck'(state, p) {
        const item = state.items.get(p.item_id);
        if (item && !item.deleted && item.checked_at) {
            const changed = edit(state.items, p.item_id);
            changed.checked_at = null;
            changed.checked_by = null;
            countPurchase(state, item.article_id, -1);
        }
    },

    'item.update'(state, p) {
        const item = state.items.get(p.item_id);
        if (!item || item.deleted) {
            return;
        }
        const changed = edit(state.items, p.item_id);
        const fields = p.fields ?? {};
        if ('name' in fields) changed.name = fields.name.trim();
        if ('note' in fields) changed.note = fields.note?.trim() || null;
        if ('store_ids' in fields) changed.store_ids = [...new Set(fields.store_ids ?? [])];
        if ('valid_until' in fields) changed.valid_until = fields.valid_until ?? null;
        if ('qty_override' in fields || 'unit_override' in fields) {
            changed.qty_override = fields.qty_override ?? null;
            changed.unit_override = changed.qty_override === null ? null : (fields.unit_override ?? null);
        }
    },

    'item.move'(state, p) {
        const item = state.items.get(p.item_id);
        if (!item || item.deleted) {
            return;
        }
        const categoryChanged = item.category_id !== p.category_id;
        const changed = edit(state.items, p.item_id);
        changed.category_id = p.category_id;
        changed.sort_key = p.sort_key;
        if (categoryChanged && item.article_id && state.articles.has(item.article_id)) {
            edit(state.articles, item.article_id).category_id = p.category_id; // Lerneffekt
        }
    },

    'item.delete'(state, p) {
        if (state.items.get(p.item_id)?.deleted === false) {
            edit(state.items, p.item_id).deleted = true;
        }
    },

    'article.create'(state, p) {
        const normalized = normalize(p.name);
        if (state.articles.has(p.article_id) || findArticleIdByTerm(state, normalized)) {
            return; // der Server führt zusammen
        }
        state.articles.set(p.article_id, {
            id: p.article_id,
            name: p.name.trim(),
            name_normalized: normalized,
            category_id: p.category_id,
            default_unit: null,
            deleted: false,
            pending: true,
        });
    },

    'article.update'(state, p) {
        if (!state.articles.has(p.article_id)) {
            return;
        }
        const article = edit(state.articles, p.article_id);
        if (p.name) {
            article.name = p.name.trim();
            article.name_normalized = normalize(p.name);
        }
        if (p.category_id) article.category_id = p.category_id;
    },

    'alias.add'(state, p) {
        const normalized = normalize(p.alias);
        if (normalized && !findArticleIdByTerm(state, normalized)) {
            state.aliases.set(p.alias_id, { id: p.alias_id, article_id: p.article_id, alias_normalized: normalized, source: 'user', deleted: false });
        }
    },
};

/** Kaufzähler des Artikels wie auf dem Server (Abhaken +1, Zurücknehmen −1) */
function countPurchase(state, articleId, delta) {
    if (articleId && state.articles.has(articleId)) {
        const article = edit(state.articles, articleId);
        article.purchase_count = Math.max(0, (article.purchase_count ?? 0) + delta);
    }
}

export function findItemByArticle(state, listId, articleId) {
    for (const item of state.items.values()) {
        if (!item.deleted && item.list_id === listId && item.article_id === articleId) {
            return item;
        }
    }

    return null;
}

export function findArticleIdByTerm(state, normalized) {
    for (const article of state.articles.values()) {
        if (!article.deleted && article.name_normalized === normalized) {
            return article.id;
        }
    }
    for (const alias of state.aliases.values()) {
        if (!alias.deleted && alias.alias_normalized === normalized && state.articles.get(alias.article_id)?.deleted === false) {
            return alias.article_id;
        }
    }

    return null;
}

/** Aktive Läden, alphabetisch */
export function activeStores(state) {
    return [...state.stores.values()]
        .filter((store) => !store.deleted)
        .sort((a, b) => a.name.localeCompare(b.name, 'de', { sensitivity: 'base' }));
}

export function activeList(state) {
    return [...state.lists.values()].find((list) => !list.deleted) ?? null;
}

export function fallbackCategory(state) {
    const categories = [...state.categories.values()].filter((category) => !category.deleted);

    return categories.find((category) => category.is_fallback) ?? categories[0] ?? null;
}

/** Zeitpunkte kommen in verschiedenen ISO-Formaten (Server mit Offset, Client mit "Z"). */
export function time(iso) {
    return iso ? Date.parse(iso) : 0;
}

function addContribution(state, itemId, payload, at, actorId) {
    const item = state.items.get(itemId);
    if (!item || item.deleted) {
        return;
    }
    const changed = edit(state.items, itemId);
    if (changed.checked_at) {
        // Reaktivieren: alte Beiträge archivieren, manuelle Menge zurücksetzen
        changed.contributions = [];
        changed.checked_at = null;
        changed.checked_by = null;
        changed.valid_until = null; // Angebot o. Ä. gilt nur für die alte Runde, der Laden bleibt
        changed.activated_at = time(at) > time(changed.activated_at) ? at : changed.activated_at;
        changed.qty_override = null;
        changed.unit_override = null;
    }
    changed.contributions = [...changed.contributions, contribution(payload, at, actorId)];
}

/** item.add für einen Artikel, der schon auf der Liste steht: Läden ergänzen, Datum setzen (wie ItemExtras::addTo) */
function addExtras(state, itemId, payload) {
    const stores = payload.store_ids ?? [];
    if (stores.length === 0 && !payload.valid_until) {
        return;
    }
    const changed = edit(state.items, itemId);
    changed.store_ids = [...new Set([...(changed.store_ids ?? []), ...stores])];
    if (payload.valid_until) {
        changed.valid_until = payload.valid_until;
    }
}

function contribution(payload, at, actorId) {
    return {
        id: payload.id,
        source: 'manual',
        source_ref: null,
        quantity: payload.quantity ?? null,
        unit: payload.unit ?? null,
        created_by: actorId,
        created_at: at,
    };
}

/** Kopie anlegen, in die Map setzen und als "noch nicht synchronisiert" markieren. */
function edit(map, id) {
    const copy = { ...map.get(id), pending: true };
    if (copy.contributions) {
        copy.contributions = [...copy.contributions];
    }
    map.set(id, copy);

    return copy;
}
