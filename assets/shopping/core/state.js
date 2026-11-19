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
};

/**
 * @param {Iterable<{type: string, data: object}>} serverEntities
 * @param {Array<{type: string, at: string, payload: object}>} outbox
 */
export function buildState(serverEntities, outbox = []) {
    const state = {
        categories: new Map(),
        articles: new Map(),
        aliases: new Map(),
        lists: new Map(),
        items: new Map(),
    };

    for (const { type, data } of serverEntities) {
        const collection = COLLECTIONS[type];
        if (collection) {
            state[collection].set(data.id, data);
        }
    }

    for (const mutation of outbox) {
        applyMutation(state, mutation);
    }

    return state;
}

/**
 * Wendet eine Mutation an. Objekte werden vor dem Ändern kopiert, der Serverzustand bleibt unberührt.
 */
export function applyMutation(state, mutation) {
    const handler = HANDLERS[mutation.type];
    if (handler) {
        handler(state, mutation.payload, mutation.at);
    }
}

const HANDLERS = {
    'item.add'(state, p, at) {
        if (state.items.has(p.item_id)) {
            return;
        }
        if (p.article_id) {
            const existing = findItemByArticle(state, p.list_id, p.article_id);
            if (existing) {
                addContribution(state, existing.id, p.contribution, at);
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
            contributions: [contribution(p.contribution, at)],
            deleted: false,
            pending: true,
        });
    },

    'item.reactivate'(state, p, at) {
        addContribution(state, p.item_id, p.contribution, at);
    },

    'item.check'(state, p, at) {
        const item = state.items.get(p.item_id);
        if (!item || item.deleted || item.checked_at || time(at) < time(item.activated_at)) {
            return;
        }
        edit(state.items, p.item_id).checked_at = at;
    },

    'item.uncheck'(state, p) {
        const item = state.items.get(p.item_id);
        if (item && !item.deleted && item.checked_at) {
            edit(state.items, p.item_id).checked_at = null;
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

function addContribution(state, itemId, payload, at) {
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
        changed.activated_at = time(at) > time(changed.activated_at) ? at : changed.activated_at;
        changed.qty_override = null;
        changed.unit_override = null;
    }
    changed.contributions = [...changed.contributions, contribution(payload, at)];
}

function contribution(payload, at) {
    return {
        id: payload.id,
        source: 'manual',
        source_ref: null,
        quantity: payload.quantity ?? null,
        unit: payload.unit ?? null,
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
