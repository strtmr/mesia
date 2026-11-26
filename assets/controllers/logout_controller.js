import { Controller } from '@hotwired/stimulus';

/**
 * Beim Abmelden die lokale Datenbank der Einkaufsliste löschen, damit auf einem geteilten Gerät nichts zurückbleibt.
 * Noch nicht synchronisierte Änderungen gehen dabei verloren.
 */
export default class extends Controller {
    logout(event) {
        event.preventDefault();
        const target = this.element.href;
        // Name wie DATABASE_NAME in shopping/core/db.js (nicht importiert, das würde Dexie auf jeder Seite laden)
        const request = indexedDB.deleteDatabase('mesia');
        const proceed = () => window.location.assign(target);
        request.onsuccess = proceed;
        request.onerror = proceed;
        request.onblocked = proceed;
    }
}
