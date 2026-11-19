import Alpine from 'alpinejs';
import { openDatabase } from '../core/db.js';
import { createApi } from '../core/api.js';
import { SyncEngine } from '../core/sync.js';
import { shoppingList } from './list.js';

/**
 * Einstieg der Offline-Einkaufsliste. Wird von app.js nur auf der Listenseite nachgeladen (einmal pro Seitenaufruf,
 * Turbo-Navigationen nutzen dieselbe Instanz weiter).
 */
const engine = new SyncEngine({ db: openDatabase(), api: createApi() });

Alpine.store('sync', { state: engine.status.state, pending: 0 });
engine.subscribe(() => {
    const store = Alpine.store('sync');
    store.state = engine.status.state;
    store.pending = engine.status.pending;
});

Alpine.data('shoppingList', () => shoppingList(engine));

window.Alpine = Alpine;
Alpine.start();
