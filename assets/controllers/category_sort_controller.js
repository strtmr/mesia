import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

/**
 * Kategorien per Ziehen am Griff sortieren. Nach dem Loslassen geht die neue Reihenfolge an den Server.
 * Klappt das nicht (offline, inzwischen geändert), lädt die Seite neu und zeigt wieder den gespeicherten Stand.
 */
export default class extends Controller {
    static values = { url: String, token: String, error: String };

    connect() {
        this.sortable = Sortable.create(this.element, {
            handle: '.category-row__handle',
            animation: 150,
            onEnd: (event) => {
                if (event.oldIndex !== event.newIndex) {
                    this.save();
                }
            },
        });
    }

    disconnect() {
        this.sortable?.destroy();
    }

    async save() {
        const ids = [...this.element.querySelectorAll('[data-id]')].map((row) => row.dataset.id);
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ ids, token: this.tokenValue }),
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
        } catch {
            window.alert(this.errorValue);
            window.location.reload();
        }
    }
}
