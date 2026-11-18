/*
 * Service Worker – Grundgerüst (Phase 0).
 *
 * Aktuell: Seitenaufrufe gehen immer ans Netz. Ohne Verbindung erscheint eine Offline-Seite.
 * Phase 1: Die App-Hülle der Einkaufsliste und ihre JS-Module werden vorab gecacht, damit die Liste offline startet.
 *
 * Bei Änderungen an dieser Datei CACHE_VERSION erhöhen, damit alte Caches entfernt werden.
 */

const CACHE_VERSION = 'v1';
const CACHE_NAME = `mealplanner-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.html';
const PRECACHE_URLS = [OFFLINE_URL, '/icons/icon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('mealplanner-') && key !== CACHE_NAME)
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    // Nur Seitenaufrufe abfangen. API-Aufrufe und Assets laufen direkt übers Netz bzw. den HTTP-Cache.
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(async () => (await caches.match(OFFLINE_URL)) ?? Response.error()),
    );
});
