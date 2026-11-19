import { normalize } from './normalize.js';
import { findArticleIdByTerm, time } from './state.js';

const PLURAL_SUFFIXES = ['en', 'n', 'e', 's'];

/**
 * Freitext → Artikel: 1. exakter Treffer (Name oder Alias), 2. Mehrzahl abschneiden und erneut exakt suchen.
 * (Spezifikation 5.3. Ähnlichkeitssuche gibt es nur als Vorschlag, nie automatisch.)
 */
export function matchArticle(state, text) {
    const normalized = normalize(text);
    if (!normalized) {
        return null;
    }

    const exact = findArticleIdByTerm(state, normalized);
    if (exact) {
        return state.articles.get(exact);
    }

    for (const suffix of PLURAL_SUFFIXES) {
        if (normalized.length > suffix.length + 2 && normalized.endsWith(suffix)) {
            const id = findArticleIdByTerm(state, normalized.slice(0, -suffix.length));
            if (id) {
                return state.articles.get(id);
            }
        }
    }

    return null;
}

/**
 * Vorschläge beim Tippen: zuerst Erledigtes (Kaufhistorie), dann Katalogartikel. Bei Tippfehlern ähnliche Namen.
 *
 * @returns {Array<{article: object, fromHistory: boolean, onList: boolean}>}
 */
export function suggest(state, query, limit = 6) {
    const q = normalize(query);
    if (q.length < 2) {
        return [];
    }

    const matches = (term) => term.startsWith(q) || term.includes(` ${q}`);
    const scores = new Map(); // articleId → Rang (kleiner = besser)

    for (const item of state.items.values()) {
        if (!item.deleted && item.article_id && item.checked_at && matches(normalize(item.name))) {
            const rank = -time(item.checked_at); // zuletzt gekauft zuerst
            scores.set(item.article_id, Math.min(scores.get(item.article_id) ?? Infinity, rank));
        }
    }

    const catalogRank = 1e15; // immer hinter der Historie
    for (const article of state.articles.values()) {
        if (!article.deleted && !scores.has(article.id) && matches(article.name_normalized)) {
            scores.set(article.id, catalogRank + (article.name_normalized.startsWith(q) ? 0 : 1) * 1000 + article.name_normalized.length);
        }
    }
    for (const alias of state.aliases.values()) {
        if (!alias.deleted && !scores.has(alias.article_id) && matches(alias.alias_normalized)) {
            scores.set(alias.article_id, catalogRank + 2000 + alias.alias_normalized.length);
        }
    }

    if (scores.size === 0 && q.length >= 4) {
        const maxDistance = q.length >= 7 ? 2 : 1;
        for (const article of state.articles.values()) {
            if (!article.deleted && levenshtein(article.name_normalized, q) <= maxDistance) {
                scores.set(article.id, catalogRank + 5000);
            }
        }
    }

    const onList = new Set(
        [...state.items.values()].filter((item) => !item.deleted && !item.checked_at).map((item) => item.article_id),
    );

    return [...scores.entries()]
        .sort((a, b) => a[1] - b[1])
        .slice(0, limit)
        .map(([id, rank]) => ({ article: state.articles.get(id), fromHistory: rank < catalogRank, onList: onList.has(id) }))
        .filter((suggestion) => suggestion.article);
}

export function levenshtein(a, b) {
    if (Math.abs(a.length - b.length) > 2) {
        return 3;
    }
    let previous = Array.from({ length: b.length + 1 }, (_, i) => i);
    for (let i = 1; i <= a.length; i++) {
        const current = [i];
        for (let j = 1; j <= b.length; j++) {
            current[j] = Math.min(previous[j] + 1, current[j - 1] + 1, previous[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
        }
        previous = current;
    }

    return previous[b.length];
}
