import Dexie from 'dexie';

// Historischer Name aus der Zeit vor der Umbenennung in Mesuna. Nicht ändern: Eine neue Datenbank wäre auf jedem Gerät leer,
// noch nicht synchronisierte Änderungen gingen verloren.
export const DATABASE_NAME = 'mesia';

/**
 * Lokale Datenbank (IndexedDB):
 * - entities: bestätigter Serverzustand, ein Datensatz je Objekt ({key: "typ:id", type, id, data})
 * - outbox:   ausstehende Mutationen in Reihenfolge (seq)
 * - meta:     cursor, deviceId, householdId, snapshotVersion, people (angemeldeter Benutzer und Haushaltsmitglieder)
 */
export function openDatabase(name = DATABASE_NAME) {
    const db = new Dexie(name);
    db.version(1).stores({
        entities: '&key, type',
        outbox: '++seq, &id',
        meta: '&key',
    });

    return db;
}

export function entityRow(type, data) {
    return { key: `${type}:${data.id}`, type, id: data.id, data };
}

export async function getMeta(db, key, fallback = null) {
    return (await db.table('meta').get(key))?.value ?? fallback;
}

export async function setMeta(db, key, value) {
    await db.table('meta').put({ key, value });
}
