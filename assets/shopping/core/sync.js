import { entityRow, getMeta, setMeta } from './db.js';
import { buildState } from './state.js';
import { HttpError, NetworkError, UnauthorizedError } from './api.js';

const SYNC_INTERVAL_MS = 8000;
const DEBOUNCE_MS = 1000;
const MAX_BACKOFF_MS = 60000;
/**
 * Erhöhen, wenn der Snapshot neue Felder bekommt, die schon vorhandene Objekte brauchen. Jedes Gerät lädt dann einmal
 * einen frischen Snapshot (2: created_by an den Beiträgen für „Hinzugefügt von“).
 */
export const SNAPSHOT_VERSION = 2;

/**
 * Hält den lokalen Zustand und gleicht ihn mit dem Server ab (Spezifikation Kapitel 4).
 *
 * Zyklus: Outbox senden (push) → Änderungen holen (pull) → bestätigte Mutationen aus der Outbox entfernen.
 * Erst danach wird neu berechnet. So "flackert" nichts zwischen Push und Pull.
 *
 * Statuswerte: idle, syncing, synced, pending, offline, unauthorized, error
 */
export class SyncEngine {
    constructor({ db, api, pullLimit = 500, pushBatchSize = 100 }) {
        this.db = db;
        this.api = api;
        this.pullLimit = pullLimit;
        this.pushBatchSize = pushBatchSize;

        this.server = new Map();
        this.outbox = [];
        this.state = buildState([]);
        /** Angemeldeter Benutzer und Haushaltsmitglieder (ID → Name), gespeichert für den Offline-Start */
        this.people = { userId: null, names: new Map() };
        this.status = { state: 'idle', pending: 0, lastSyncAt: null };
        this.rejected = [];

        this.listeners = new Set();
        this.initPromise = null;
        this.running = null;
        this.again = false;
        this.householdChecked = false;
        this.failures = 0;
        this.retryAt = 0;
        this.attachments = 0;
        this.debounceTimer = null;
    }

    /** Lädt den lokalen Stand aus IndexedDB (einmalig, funktioniert offline). */
    init() {
        this.initPromise ??= this.reload();

        return this.initPromise;
    }

    subscribe(listener) {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    }

    /** Hat das Gerät schon einmal Daten vom Server erhalten? */
    get hasData() {
        return this.server.size > 0;
    }

    /**
     * Mutationen lokal übernehmen und zum Senden vormerken.
     */
    async execute(mutations) {
        if (mutations.length === 0) {
            return;
        }
        await this.init();
        await this.db.table('outbox').bulkAdd(mutations.map((mutation) => ({ ...mutation })));
        this.outbox = await this.db.table('outbox').orderBy('seq').toArray();
        this.recompute();
        this.scheduleSync(DEBOUNCE_MS);
    }

    /**
     * Ein Sync-Zyklus. Läuft schon einer, wird danach ein weiterer gestartet.
     */
    async sync() {
        if (this.running) {
            this.again = true;
            return this.running;
        }

        this.running = this.runCycle().finally(() => {
            this.running = null;
            if (this.again) {
                this.again = false;
                this.sync();
            }
        });

        return this.running;
    }

    async runCycle() {
        await this.init();
        this.setStatus('syncing');

        try {
            await this.ensureHousehold();
            if ((await getMeta(this.db, 'cursor')) === null || (await getMeta(this.db, 'snapshotVersion')) !== SNAPSHOT_VERSION) {
                await this.loadSnapshot();
            }

            const pending = await this.db.table('outbox').orderBy('seq').toArray();
            const results = await this.push(pending);
            await this.pullAll();
            await this.acknowledge(pending, results);

            await this.reload();
            this.failures = 0;
            this.retryAt = 0;
            this.status.lastSyncAt = new Date().toISOString();
            this.setStatus(this.outbox.length > 0 ? 'pending' : 'synced');
        } catch (error) {
            this.failures++;
            this.retryAt = Date.now() + Math.min(MAX_BACKOFF_MS, 2000 * 2 ** (this.failures - 1));
            if (error instanceof UnauthorizedError) {
                this.setStatus('unauthorized');
            } else if (error instanceof NetworkError) {
                this.setStatus('offline');
            } else {
                console.error('Sync fehlgeschlagen', error);
                this.setStatus('error');
            }
        }
    }

    // ------------------------------------------------------------------ Lebenszyklus (Timer, Browser-Events)

    /** Automatischen Sync starten (beim Öffnen der Liste). Mehrfaches attach() wird gezählt. */
    attach() {
        if (this.attachments++ > 0) {
            return;
        }
        this.onVisible = () => document.visibilityState === 'visible' && this.sync();
        this.onOnline = () => {
            this.retryAt = 0;
            this.sync();
        };
        document.addEventListener('visibilitychange', this.onVisible);
        window.addEventListener('online', this.onOnline);
        this.interval = setInterval(() => {
            if (document.visibilityState === 'visible' && Date.now() >= this.retryAt) {
                this.sync();
            }
        }, SYNC_INTERVAL_MS);
        this.sync();
    }

    detach() {
        if (--this.attachments > 0) {
            return;
        }
        this.attachments = 0;
        document.removeEventListener('visibilitychange', this.onVisible);
        window.removeEventListener('online', this.onOnline);
        clearInterval(this.interval);
        clearTimeout(this.debounceTimer);
    }

    scheduleSync(delay) {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => this.sync(), delay);
    }

    // ------------------------------------------------------------------ Schritte des Zyklus

    /** Anderer Haushalt als zuletzt (z. B. anderes Konto)? Dann lokale Daten verwerfen. */
    async ensureHousehold() {
        if (this.householdChecked) {
            return;
        }
        const me = await this.api.me();
        const known = await getMeta(this.db, 'householdId');
        if (known !== null && known !== me.household.id) {
            await this.db.transaction('rw', 'entities', 'outbox', 'meta', async () => {
                await this.db.table('entities').clear();
                await this.db.table('outbox').clear();
                await this.db.table('meta').delete('cursor');
            });
        }
        await setMeta(this.db, 'householdId', me.household.id);
        await setMeta(this.db, 'people', {
            userId: me.id ?? null,
            members: (me.household.members ?? []).map((member) => ({ id: member.id, name: member.display_name })),
        });
        this.householdChecked = true;
    }

    async loadSnapshot() {
        const snapshot = await this.api.snapshot();
        const rows = Object.entries(snapshot.entities).flatMap(([type, list]) => list.map((data) => entityRow(type, data)));

        await this.db.transaction('rw', 'entities', 'meta', async () => {
            await this.db.table('entities').clear();
            await this.db.table('entities').bulkPut(rows);
            await setMeta(this.db, 'cursor', snapshot.cursor);
            await setMeta(this.db, 'snapshotVersion', SNAPSHOT_VERSION);
        });
    }

    async push(pending) {
        if (pending.length === 0) {
            return [];
        }
        const deviceId = await this.deviceId();
        const results = [];
        const remap = new Map();
        for (let i = 0; i < pending.length; i += this.pushBatchSize) {
            const slice = pending.slice(i, i + this.pushBatchSize);
            // Umlenkungen aus früheren Batches anwenden (innerhalb eines Batches erledigt das der Server)
            const batch = slice.map(({ id, type, at, payload }) => ({ id, type, at, payload: remapIds(payload, remap) }));
            const response = await this.api.push(deviceId, batch);
            collectRemaps(batch, response.results, remap);
            results.push(...response.results);
        }

        return results;
    }

    async pullAll() {
        let cursor = await getMeta(this.db, 'cursor', 0);
        let hasMore = true;

        while (hasMore) {
            let page;
            try {
                page = await this.api.pull(cursor, this.pullLimit);
            } catch (error) {
                if (error instanceof HttpError && error.status === 410) {
                    await this.loadSnapshot(); // Protokoll wurde bereinigt: neu befüllen
                    return;
                }
                throw error;
            }

            await this.db.transaction('rw', 'entities', 'meta', async () => {
                if (page.changes.length > 0) {
                    await this.db.table('entities').bulkPut(page.changes.map((change) => entityRow(change.type, change.data)));
                }
                await setMeta(this.db, 'cursor', page.cursor);
            });
            cursor = page.cursor;
            hasMore = page.has_more;
        }
    }

    /**
     * Bestätigte Mutationen aus der Outbox entfernen. Bei "merged" wurde ein lokal angelegtes Objekt auf ein
     * bestehendes umgelenkt: Spätere Mutationen in der Outbox müssen dann auf die neue ID zeigen.
     */
    async acknowledge(pending, results) {
        const resultById = new Map(results.map((result) => [result.id, result]));
        const done = [];
        const remap = new Map();
        const rejected = [];

        collectRemaps(pending, results, remap);

        for (const mutation of pending) {
            const result = resultById.get(mutation.id);
            if (!result) {
                continue;
            }
            done.push(mutation.seq);

            if (result.status === 'rejected' || result.original_status === 'rejected') {
                rejected.push({ type: mutation.type, name: mutation.payload?.name ?? null, error: result.error ?? 'Unbekannter Fehler' });
            }
        }

        await this.db.transaction('rw', 'outbox', async () => {
            await this.db.table('outbox').bulkDelete(done);
            if (remap.size > 0) {
                for (const mutation of await this.db.table('outbox').toArray()) {
                    const payload = remapIds(mutation.payload, remap);
                    if (payload !== mutation.payload) {
                        await this.db.table('outbox').update(mutation.seq, { payload });
                    }
                }
            }
        });

        if (rejected.length > 0) {
            this.rejected = rejected;
        }
    }

    // ------------------------------------------------------------------ intern

    async reload() {
        const rows = await this.db.table('entities').toArray();
        this.server = new Map(rows.map((row) => [row.key, row]));
        this.outbox = await this.db.table('outbox').orderBy('seq').toArray();
        const people = await getMeta(this.db, 'people', { userId: null, members: [] });
        this.people = { userId: people.userId, names: new Map(people.members.map((member) => [member.id, member.name])) };
        this.recompute();
    }

    recompute() {
        this.state = buildState(this.server.values(), this.outbox, { actorId: this.people.userId });
        this.status.pending = this.outbox.length;
        // Lokale Änderung seit dem letzten Sync → "ausstehend" statt "synchron" anzeigen
        if (this.outbox.length > 0 && ['synced', 'idle'].includes(this.status.state)) {
            this.status.state = 'pending';
        }
        this.emit();
    }

    setStatus(state) {
        this.status.state = state;
        this.emit();
    }

    emit() {
        for (const listener of this.listeners) {
            listener(this);
        }
    }

    /** Liefert die bisherigen Ablehnungen und leert die Liste (zum Anzeigen als Hinweis). */
    takeRejected() {
        const rejected = this.rejected;
        this.rejected = [];

        return rejected;
    }

    async deviceId() {
        let id = await getMeta(this.db, 'deviceId');
        if (id === null) {
            id = crypto.randomUUID();
            await setMeta(this.db, 'deviceId', id);
        }

        return id;
    }
}

const REMAPPED_KEYS = ['item_id', 'article_id'];

/** Lokale ID eines neu angelegten Objekts → ID, in die der Server zusammengeführt hat. */
function collectRemaps(mutations, results, remap) {
    const resultById = new Map(results.map((result) => [result.id, result]));
    for (const mutation of mutations) {
        const mergedInto = resultById.get(mutation.id)?.merged_into;
        const localId = mutation.type === 'item.add' ? mutation.payload.item_id
            : mutation.type === 'article.create' ? mutation.payload.article_id
                : null;
        if (mergedInto && localId && localId !== mergedInto) {
            remap.set(localId, mergedInto);
        }
    }
}

function remapIds(payload, remap) {
    let changed = null;
    for (const key of REMAPPED_KEYS) {
        if (payload[key] && remap.has(payload[key])) {
            changed ??= { ...payload };
            changed[key] = remap.get(payload[key]);
        }
    }

    return changed ?? payload;
}
