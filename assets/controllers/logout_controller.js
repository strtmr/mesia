import { Controller } from '@hotwired/stimulus';

/**
 * Beim Abmelden die lokale Datenbank der Einkaufsliste löschen, damit auf einem geteilten Gerät nichts zurückbleibt.
 * Noch nicht synchronisierte Änderungen gehen dabei verloren.
 */
export default class extends Controller {
    logout(event) {
        event.preventDefault();
        const target = this.element.href;
        const request = indexedDB.deleteDatabase('mealplanner');
        const proceed = () => window.location.assign(target);
        request.onsuccess = proceed;
        request.onerror = proceed;
        request.onblocked = proceed;
    }
}
