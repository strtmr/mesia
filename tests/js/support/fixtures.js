/**
 * Kleiner Testkatalog im Format der Server-Snapshots.
 */
export const LIST_ID = 'list-1';

export function catalogEntities() {
    return [
        { type: 'shopping_list', data: { id: LIST_ID, name: 'Einkaufsliste', deleted: false } },
        category('cat-produce', 'Obst & Gemüse', 'a0'),
        category('cat-dairy', 'Milchprodukte & Eier', 'a1'),
        category('cat-drinks', 'Getränke', 'a2'),
        category('cat-other', 'Sonstiges', 'a3', true),
        article('art-milch', 'Milch', 'milch', 'cat-dairy'),
        article('art-butter', 'Butter', 'butter', 'cat-dairy'),
        article('art-banane', 'Banane', 'banane', 'cat-produce'),
        article('art-karotte', 'Karotte', 'karotte', 'cat-produce'),
        article('art-zwiebel', 'Zwiebel', 'zwiebel', 'cat-produce'),
        article('art-apfel', 'Apfel', 'apfel', 'cat-produce'),
        alias('ali-moehre', 'art-karotte', 'moehre'),
        alias('ali-aepfel', 'art-apfel', 'aepfel'),
        alias('ali-vollmilch', 'art-milch', 'vollmilch'),
    ];
}

export function category(id, name, sortKey, isFallback = false) {
    return { type: 'category', data: { id, name, icon: '•', sort_key: sortKey, is_fallback: isFallback, deleted: false } };
}

export function article(id, name, normalized, categoryId) {
    return { type: 'article', data: { id, name, name_normalized: normalized, category_id: categoryId, default_unit: null, deleted: false } };
}

export function alias(id, articleId, normalized) {
    return { type: 'article_alias', data: { id, article_id: articleId, alias_normalized: normalized, source: 'seed', deleted: false } };
}

/** Deterministischer Kontext für Kommandos: IDs id-1, id-2, … und feste Zeit (anpassbar). */
export function testContext(start = '2026-11-20T10:00:00.000Z') {
    let counter = 0;
    let now = start;

    return {
        newId: () => `id-${++counter}`,
        now: () => now,
        setNow: (iso) => { now = iso; },
    };
}
