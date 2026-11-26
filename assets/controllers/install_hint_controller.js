import { Controller } from '@hotwired/stimulus';

// Historischer Name aus der Zeit vor der Umbenennung in Mesuna. Nicht ändern: gespeichert auf den Geräten (localStorage).
const STORAGE_KEY = 'mesia.installHintDismissed';

/**
 * Zeigt auf iPhone/iPad in Safari einen Hinweis, die App zum Home-Bildschirm hinzuzufügen.
 *
 * Warum? Nur als Home-Bildschirm-App läuft sie ohne Browserleiste, und iOS behandelt ihre lokalen Daten
 * (wichtig für die Offline-Liste) dauerhafter.
 */
export default class extends Controller {
    connect() {
        if (isIos() && !isStandalone() && !isDismissed()) {
            this.element.hidden = false;
        }
    }

    dismiss() {
        this.element.hidden = true;
        try {
            localStorage.setItem(STORAGE_KEY, '1');
        } catch {
            // Privater Modus o. ä.: Hinweis erscheint dann beim nächsten Mal wieder.
        }
    }
}

function isIos() {
    const ua = navigator.userAgent;
    // iPadOS meldet sich als "Macintosh", hat aber Touch.
    return /iPhone|iPad|iPod/.test(ua) || (ua.includes('Macintosh') && navigator.maxTouchPoints > 1);
}

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
}

function isDismissed() {
    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}
