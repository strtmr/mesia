import { Controller } from '@hotwired/stimulus';
import { UPDATE_EVENT } from '../pwa/register-service-worker.js';

/**
 * Hinweis "Neue Version verfügbar" mit Knopf zum Neuladen.
 *
 * Neu laden ist jederzeit gefahrlos: Noch nicht übertragene Änderungen liegen in IndexedDB.
 */
export default class extends Controller {
    connect() {
        this.show = () => {
            this.element.hidden = false;
        };
        // Die Meldung kann vor diesem Controller gekommen sein (z. B. vor einer Turbo-Navigation)
        if (document.documentElement.dataset.updateAvailable) {
            this.show();
        }
        window.addEventListener(UPDATE_EVENT, this.show);
    }

    disconnect() {
        window.removeEventListener(UPDATE_EVENT, this.show);
    }

    reload() {
        window.location.reload();
    }
}
