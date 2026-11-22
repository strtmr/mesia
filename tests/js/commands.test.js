import { describe, expect, it } from 'vitest';
import { buildState, findItemByArticle } from '../../assets/shopping/core/state.js';
import { buildListView } from '../../assets/shopping/core/view.js';
import { TextTooLongError, addFromText, checkItem, editItem, moveItem, reactivateItem, uncheckItem } from '../../assets/shopping/core/commands.js';
import { QuantityError } from '../../assets/shopping/core/quantity.js';
import { catalogEntities } from './support/fixtures.js';

/** Hilfsfunktion: Kommandos nacheinander ausführen, wie die Outbox es tut. */
function run(entities, steps) {
    let outbox = [];
    let state = buildState(entities);
    for (const step of steps) {
        outbox = [...outbox, ...step(state)];
        state = buildState(entities, outbox);
    }

    return { state, outbox, view: buildListView(state) };
}

describe('Schnelleingabe → Liste', () => {
    it('legt einen bekannten Artikel mit Menge in seiner Kategorie an', () => {
        const ctx = testCtx();
        const { view, outbox } = run(catalogEntities(), [(s) => addFromText(s, '2 l Milch', ctx)]);

        expect(outbox.map((m) => m.type)).toEqual(['item.add']);
        expect(view.groups).toHaveLength(1);
        expect(view.groups[0].category.id).toBe('cat-dairy');
        expect(view.groups[0].items[0]).toMatchObject({ name: 'Milch', quantityLabel: '2 l', pending: true });
    });

    it('rechnet Mengen aus ("2x500g")', () => {
        const ctx = testCtx();
        const { outbox } = run(catalogEntities(), [(s) => addFromText(s, '2x500g Milch', ctx)]);

        expect(outbox[0].payload.contribution).toMatchObject({ quantity: 1000, unit: 'g' });
    });

    it('legt bei unplausibler Menge nichts an', () => {
        const ctx = testCtx();
        const state = buildState(catalogEntities());

        expect(() => addFromText(state, '1111111111 Milch', ctx)).toThrow(QuantityError);
        expect(() => addFromText(state, '1/0 Milch', ctx)).toThrow(QuantityError);
    });

    it('lehnt zu lange Namen und Notizen ab (Grenzen wie auf dem Server)', () => {
        const ctx = testCtx();
        const state = buildState(catalogEntities());

        expect(() => addFromText(state, 'x'.repeat(101), ctx)).toThrow(TextTooLongError);
        expect(() => addFromText(state, `Milch (${'n'.repeat(201)})`, ctx)).toThrow(TextTooLongError);
        expect(addFromText(state, `2 l ${'Ü'.repeat(100)}`, ctx).length).toBeGreaterThan(0); // Menge zählt nicht mit
    });

    it('legt Unbekanntes als neuen Artikel in "Sonstiges" an', () => {
        const ctx = testCtx();
        const { view, outbox, state } = run(catalogEntities(), [(s) => addFromText(s, 'drachenfrucht', ctx)]);

        expect(outbox.map((m) => m.type)).toEqual(['article.create', 'item.add']);
        expect(view.groups[0].category.id).toBe('cat-other');
        expect(view.groups[0].items[0].name).toBe('Drachenfrucht');
        expect([...state.articles.values()].some((a) => a.name_normalized === 'drachenfrucht')).toBe(true);
    });

    it('führt denselben Artikel zusammen und summiert gleiche Einheiten', () => {
        const ctx = testCtx();
        const { view } = run(catalogEntities(), [
            (s) => addFromText(s, '500 g Butter', ctx),
            (s) => addFromText(s, 'Butter 250g', ctx),
            (s) => addFromText(s, '1 Pck Butter', ctx),
        ]);

        expect(view.openCount).toBe(1);
        expect(view.groups[0].items[0].quantityLabel).toBe('750 g + 1 Pck');
    });

    it('reiht neue Einträge am Ende ihrer Kategorie ein', () => {
        const ctx = testCtx();
        const { view } = run(catalogEntities(), [
            (s) => addFromText(s, 'Milch', ctx),
            (s) => addFromText(s, 'Butter', ctx),
        ]);

        expect(view.groups[0].items.map((i) => i.name)).toEqual(['Milch', 'Butter']);
    });
});

describe('Abhaken, Rückgängig, Reaktivieren', () => {
    it('sortiert "Erledigt" nach Häufigkeit, bei Gleichstand das zuletzt Gekaufte zuerst', () => {
        const ctx = testCtx();
        const id = (s, articleId) => findItemByArticle(s, 'list-1', articleId).id;
        const at = (minute) => () => {
            ctx.setNow(`2026-11-20T10:0${minute}:00.000Z`);
            return [];
        };
        const { view, state } = run(catalogEntities(), [
            (s) => addFromText(s, 'Milch', ctx),
            (s) => addFromText(s, 'Butter', ctx),
            (s) => addFromText(s, 'Apfel', ctx),
            at(1), (s) => checkItem(id(s, 'art-milch'), ctx),
            at(2), (s) => reactivateItem(id(s, 'art-milch'), ctx),
            at(3), (s) => checkItem(id(s, 'art-milch'), ctx),
            at(4), (s) => checkItem(id(s, 'art-apfel'), ctx),
            at(5), (s) => checkItem(id(s, 'art-butter'), ctx),
        ]);

        expect(view.done.map((item) => item.name)).toEqual(['Milch', 'Butter', 'Apfel']);
        expect(state.articles.get('art-milch').purchase_count).toBe(2);
    });

    it('nimmt den Kauf beim direkten Zurücknehmen wieder aus der Zählung', () => {
        const ctx = testCtx();
        const id = (s) => findItemByArticle(s, 'list-1', 'art-milch').id;
        const { state } = run(catalogEntities(), [
            (s) => addFromText(s, 'Milch', ctx),
            (s) => checkItem(id(s), ctx),
            (s) => uncheckItem(id(s), ctx),
        ]);

        expect(state.articles.get('art-milch').purchase_count).toBe(0);
    });

    it('verschiebt Abgehaktes nach "Erledigt" und zurück', () => {
        const ctx = testCtx();
        let itemId;
        const { view } = run(catalogEntities(), [
            (s) => {
                const mutations = addFromText(s, '2 l Milch', ctx);
                itemId = mutations.at(-1).payload.item_id;
                return mutations;
            },
            () => checkItem(itemId, ctx),
        ]);
        expect(view.openCount).toBe(0);
        expect(view.done.map((i) => i.id)).toEqual([itemId]);

        const undone = run(catalogEntities(), [
            (s) => addFromText(s, '2 l Milch', testCtx()),
            (s) => checkItem([...s.items.keys()][0], ctx),
            (s) => uncheckItem([...s.items.keys()][0], ctx),
        ]);
        expect(undone.view.openCount).toBe(1);
        expect(undone.view.groups[0].items[0].quantityLabel).toBe('2 l');
    });

    it('reaktiviert denselben Eintrag ohne alte Menge, wenn ein erledigter Artikel erneut hinzugefügt wird', () => {
        const ctx = testCtx();
        const { view, state } = run(catalogEntities(), [
            (s) => addFromText(s, '2 l Milch', ctx),
            (s) => checkItem([...s.items.keys()][0], ctx),
            (s) => { ctx.setNow('2026-11-20T11:00:00.000Z'); return addFromText(s, 'Milch', ctx); },
        ]);

        expect(state.items.size).toBe(1);
        expect(view.done).toHaveLength(0);
        expect(view.groups[0].items[0].quantityLabel).toBe('');
    });

    it('ignoriert ein Abhaken, das älter ist als das Reaktivieren', () => {
        const ctx = testCtx();
        const { view } = run(catalogEntities(), [
            (s) => addFromText(s, 'Milch', ctx),
            (s) => { ctx.setNow('2026-11-20T10:05:00.000Z'); return checkItem([...s.items.keys()][0], ctx); },
            (s) => { ctx.setNow('2026-11-20T10:20:00.000Z'); return reactivateItem([...s.items.keys()][0], ctx); },
            (s) => { ctx.setNow('2026-11-20T10:10:00.000Z'); return checkItem([...s.items.keys()][0], ctx); },
        ]);

        expect(view.openCount).toBe(1);
    });
});

describe('Bearbeiten und Verschieben', () => {
    it('sendet nur geänderte Felder und verschiebt bei Kategoriewechsel (Lerneffekt)', () => {
        const ctx = testCtx();
        const { state } = run(catalogEntities(), [(s) => addFromText(s, '2 l Milch', ctx)]);
        const item = [...state.items.values()][0];

        const mutations = editItem(state, item, { name: 'Milch', note: 'laktosefrei', quantity: 3, unit: 'l', categoryId: 'cat-drinks' }, { quantity: 2, unit: 'l' }, ctx);

        expect(mutations.map((m) => m.type)).toEqual(['item.update', 'item.move']);
        expect(mutations[0].payload.fields).toEqual({ note: 'laktosefrei', qty_override: 3, unit_override: 'l' });
    });

    it('übernimmt beim Verschieben die neue Kategorie auch für den Artikel', () => {
        const ctx = testCtx();
        const { state, view } = run(catalogEntities(), [
            (s) => addFromText(s, 'Milch', ctx),
            (s) => moveItem(s, [...s.items.keys()][0], 'cat-drinks', null, null, ctx),
        ]);

        expect(view.groups[0].category.id).toBe('cat-drinks');
        expect(state.articles.get('art-milch').category_id).toBe('cat-drinks');
    });

    it('sortiert per Drag & Drop zwischen zwei Nachbarn ein', () => {
        const ctx = testCtx();
        const { view } = run(catalogEntities(), [
            (s) => addFromText(s, 'Milch', ctx),
            (s) => addFromText(s, 'Butter', ctx),
            (s) => {
                const [milk, butter] = [...s.items.values()];
                return moveItem(s, butter.id, 'cat-dairy', null, milk.id, ctx);
            },
        ]);

        expect(view.groups[0].items.map((i) => i.name)).toEqual(['Butter', 'Milch']);
    });
});

function testCtx() {
    let counter = 0;
    let now = '2026-11-20T10:00:00.000Z';

    return { newId: () => `id-${++counter}-${Math.random().toString(36).slice(2, 7)}`, now: () => now, setNow: (iso) => { now = iso; } };
}
