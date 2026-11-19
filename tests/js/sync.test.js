import 'fake-indexeddb/auto';
import { beforeEach, describe, expect, it } from 'vitest';
import { openDatabase } from '../../assets/shopping/core/db.js';
import { SyncEngine } from '../../assets/shopping/core/sync.js';
import { NetworkError, UnauthorizedError } from '../../assets/shopping/core/api.js';
import { addFromText, checkItem } from '../../assets/shopping/core/commands.js';
import { buildListView } from '../../assets/shopping/core/view.js';
import { catalogEntities, testContext } from './support/fixtures.js';

/**
 * Minimaler Fake-Server: wendet item.add/check an wie der echte und führt ein Änderungsprotokoll.
 */
function fakeServer({ household = 'h1' } = {}) {
    const log = [];
    const entities = new Map();
    const server = {
        household,
        offline: false,
        unauthorized: false,
        pushed: [],
        mergeNextAddInto: null,
        record(type, data) {
            entities.set(`${type}:${data.id}`, { type, data });
            log.push({ seq: log.length + 1, type, id: data.id, op: data.deleted ? 'delete' : 'upsert', data });
        },
    };
    for (const { type, data } of catalogEntities()) {
        server.record(type, data);
    }

    const guard = () => {
        if (server.offline) throw new NetworkError('offline');
        if (server.unauthorized) throw new UnauthorizedError('401');
    };

    server.api = {
        async me() { guard(); return { household: { id: server.household } }; },
        async snapshot() {
            guard();
            const grouped = {};
            for (const { type, data } of entities.values()) (grouped[type] ??= []).push(data);
            return { cursor: log.length, entities: grouped };
        },
        async push(deviceId, mutations) {
            guard();
            server.pushed.push(...mutations);
            return {
                results: mutations.map((m) => {
                    if (m.type === 'item.add' && server.mergeNextAddInto) {
                        const into = server.mergeNextAddInto;
                        server.mergeNextAddInto = null;
                        return { id: m.id, status: 'merged', merged_into: into };
                    }
                    if (m.type === 'item.add') {
                        const p = m.payload;
                        server.record('shopping_item', {
                            id: p.item_id, list_id: p.list_id, article_id: p.article_id, name: p.name, category_id: p.category_id,
                            note: null, qty_override: null, unit_override: null, sort_key: p.sort_key, checked_at: null,
                            activated_at: m.at, contributions: [{ id: p.contribution.id, quantity: p.contribution.quantity, unit: p.contribution.unit }], deleted: false,
                        });
                        return { id: m.id, status: 'applied' };
                    }
                    if (m.type === 'item.check') {
                        const item = entities.get(`shopping_item:${m.payload.item_id}`);
                        if (!item) return { id: m.id, status: 'rejected', error: 'Eintrag nicht gefunden' };
                        server.record('shopping_item', { ...item.data, checked_at: m.at });
                        return { id: m.id, status: 'applied' };
                    }
                    return { id: m.id, status: 'rejected', error: `Unbekannt: ${m.type}` };
                }),
            };
        },
        async pull(since, limit) {
            guard();
            const changes = log.filter((c) => c.seq > since).slice(0, limit);
            return { changes, cursor: changes.at(-1)?.seq ?? since, has_more: log.filter((c) => c.seq > since).length > limit };
        },
    };

    return server;
}

let dbCounter = 0;

describe('SyncEngine', () => {
    let server;
    let engine;
    let ctx;

    beforeEach(async () => {
        server = fakeServer();
        engine = new SyncEngine({ db: openDatabase(`test-${++dbCounter}`), api: server.api, pullLimit: 5 });
        ctx = testContext();
        await engine.init();
    });

    it('befüllt beim ersten Sync per Snapshot und arbeitet danach mit Pull', async () => {
        await engine.sync();

        expect(engine.status.state).toBe('synced');
        expect(engine.state.articles.size).toBe(6);
        expect(engine.state.lists.size).toBe(1);
    });

    it('zeigt Änderungen sofort und leert die Outbox nach erfolgreichem Sync', async () => {
        await engine.sync();
        await engine.execute(addFromText(engine.state, '2 l Milch', ctx));

        expect(engine.status.pending).toBe(1);
        expect(engine.status.state).toBe('pending');
        expect(buildListView(engine.state).groups[0].items[0]).toMatchObject({ name: 'Milch', pending: true });

        await engine.sync();

        expect(engine.status.pending).toBe(0);
        expect(engine.status.state).toBe('synced');
        expect(buildListView(engine.state).groups[0].items[0]).toMatchObject({ name: 'Milch', quantityLabel: '2 l', pending: false });
    });

    it('arbeitet offline weiter und sendet später', async () => {
        await engine.sync();
        server.offline = true;

        await engine.execute(addFromText(engine.state, 'Butter', ctx));
        await engine.sync();

        expect(engine.status.state).toBe('offline');
        expect(engine.status.pending).toBe(1);
        expect(buildListView(engine.state).openCount).toBe(1);

        server.offline = false;
        await engine.sync();

        expect(engine.status.pending).toBe(0);
        expect(server.pushed.map((m) => m.type)).toEqual(['item.add']);
    });

    it('behält die Outbox bei abgelaufener Sitzung', async () => {
        await engine.sync();
        server.unauthorized = true;
        await engine.execute(addFromText(engine.state, 'Butter', ctx));

        await engine.sync();

        expect(engine.status.state).toBe('unauthorized');
        expect(engine.status.pending).toBe(1);
    });

    it('lenkt spätere Mutationen nach einem "merged" auf die bestehende ID um', async () => {
        await engine.sync();
        // Das andere Gerät hat Milch schon angelegt
        await server.api.push('other', addFromText(engine.state, 'Milch', testContext()).map((m) => ({ ...m, payload: { ...m.payload, item_id: 'server-milk' } })));
        server.mergeNextAddInto = 'server-milk';

        // Offline: Milch hinzufügen und gleich abhaken (beides mit der lokalen ID)
        server.offline = true;
        const add = addFromText(engine.state, 'Milch', ctx);
        await engine.execute(add);
        await engine.execute(checkItem(add[0].payload.item_id, ctx));
        await engine.sync();
        server.offline = false;

        // Ein Batch pro Mutation: Die Umlenkung muss der Client zwischen den Batches anwenden
        engine.pushBatchSize = 1;
        await engine.sync();

        expect(engine.takeRejected()).toEqual([]);
        expect(server.pushed.at(-1)).toMatchObject({ type: 'item.check', payload: { item_id: 'server-milk' } });
        expect(engine.status.pending).toBe(0);
        expect(buildListView(engine.state).done.map((item) => item.id)).toEqual(['server-milk']);
    });

    it('verwirft vom Server abgelehnte Mutationen und meldet sie', async () => {
        await engine.sync();
        await engine.execute(checkItem('gibt-es-nicht', ctx));

        await engine.sync();

        expect(engine.status.pending).toBe(0);
        expect(engine.takeRejected()).toHaveLength(1);
    });

    it('verwirft lokale Daten, wenn sich der Haushalt geändert hat', async () => {
        await engine.sync();
        await engine.execute(addFromText(engine.state, 'Butter', ctx));
        server.offline = true;
        await engine.sync();

        const otherServer = fakeServer({ household: 'h2' });
        const second = new SyncEngine({ db: engine.db, api: otherServer.api });
        await second.sync();

        expect(second.status.pending).toBe(0);
        expect(otherServer.pushed).toEqual([]);
    });
});
