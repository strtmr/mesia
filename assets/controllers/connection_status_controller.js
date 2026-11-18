import { Controller } from '@hotwired/stimulus';

/**
 * Zeigt an, ob das Gerät gerade online ist (navigator.onLine).
 * In Phase 1 kommt eine echte Sync-Statusanzeige hinzu.
 */
export default class extends Controller {
    static targets = ['label'];
    static values = { online: String, offline: String };

    connect() {
        this.update = this.update.bind(this);
        window.addEventListener('online', this.update);
        window.addEventListener('offline', this.update);
        this.update();
    }

    disconnect() {
        window.removeEventListener('online', this.update);
        window.removeEventListener('offline', this.update);
    }

    update() {
        this.labelTarget.textContent = navigator.onLine ? this.onlineValue : this.offlineValue;
    }
}
