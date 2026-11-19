import { describe, expect, it } from 'vitest';
import { buildState } from '../../assets/shopping/core/state.js';
import { matchArticle, suggest } from '../../assets/shopping/core/matcher.js';
import { catalogEntities, LIST_ID } from './support/fixtures.js';

const state = buildState(catalogEntities());

describe('matchArticle', () => {
    it.each([
        ['Milch', 'art-milch'],
        ['MILCH', 'art-milch'],
        ['Vollmilch', 'art-milch'],
        ['Möhre', 'art-karotte'],
        ['Bananen', 'art-banane'],
        ['Zwiebeln', 'art-zwiebel'],
        ['Äpfel', 'art-apfel'],
    ])('%s → %s', (text, articleId) => {
        expect(matchArticle(state, text)?.id).toBe(articleId);
    });

    it('findet nichts für Unbekanntes', () => {
        expect(matchArticle(state, 'Drachenfrucht')).toBeNull();
        expect(matchArticle(state, '')).toBeNull();
    });
});

describe('suggest', () => {
    it('schlägt Katalogartikel per Präfix und Alias vor', () => {
        expect(suggest(state, 'mi').map((s) => s.article.id)).toEqual(['art-milch']);
        expect(suggest(state, 'moe').map((s) => s.article.id)).toEqual(['art-karotte']);
    });

    it('zeigt Erledigtes (Historie) vor dem Katalog', () => {
        const withHistory = buildState([
            ...catalogEntities(),
            { type: 'shopping_item', data: item('i1', 'art-butter', 'Butter', '2026-11-19T10:00:00Z') },
        ]);

        const suggestions = suggest(withHistory, 'bu');

        expect(suggestions[0]).toMatchObject({ fromHistory: true });
        expect(suggestions[0].article.id).toBe('art-butter');
    });

    it('verzeiht Tippfehler ab vier Zeichen', () => {
        expect(suggest(state, 'Buter').map((s) => s.article.id)).toEqual(['art-butter']);
    });

    it('braucht mindestens zwei Zeichen', () => {
        expect(suggest(state, 'm')).toEqual([]);
    });
});

function item(id, articleId, name, checkedAt) {
    return {
        id, list_id: LIST_ID, article_id: articleId, name, category_id: 'cat-dairy', note: null, qty_override: null,
        unit_override: null, sort_key: 'a0', checked_at: checkedAt, activated_at: '2026-11-18T10:00:00Z', contributions: [], deleted: false,
    };
}
