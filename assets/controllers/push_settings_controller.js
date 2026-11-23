import { Controller } from '@hotwired/stimulus';

/**
 * "Mehr → Mitteilungen": Push-Abo dieses Geräts ein- und ausschalten, Häufigkeit wählen.
 *
 * Ablauf: Erlaubnis anfragen (nur nach einem Tipp möglich) → beim Push-Dienst des Browsers abonnieren →
 * Abo an den Server (/api/push/subscription). Auf dem iPhone gibt es Push nur in der installierten App.
 */
export default class extends Controller {
    static targets = ['toggle', 'frequency', 'frequencyRow', 'hint'];
    static values = {
        statusUrl: String,
        subscriptionUrl: String,
        textUnsupported: String,
        textNotConfigured: String,
        textDenied: String,
        textError: String,
    };

    async connect() {
        this.toggleTarget.disabled = true;
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            return this.showHint(this.textUnsupportedValue);
        }

        try {
            this.registration = await navigator.serviceWorker.ready;
            const subscription = await this.registration.pushManager.getSubscription();
            const status = await this.api('GET', this.statusUrlValue + (subscription ? `?endpoint=${encodeURIComponent(subscription.endpoint)}` : ''));
            if (!status.enabled) {
                return this.showHint(this.textNotConfiguredValue);
            }
            this.publicKey = status.public_key;
            if (Notification.permission === 'denied') {
                this.showHint(this.textDeniedValue);
            }
            this.toggleTarget.checked = Boolean(subscription && status.subscribed);
            if (status.frequency) {
                this.frequencyTarget.value = status.frequency;
            }
            this.toggleTarget.disabled = Notification.permission === 'denied';
            this.updateFrequencyRow();
        } catch {
            this.showHint(this.textErrorValue);
        }
    }

    async toggle() {
        this.toggleTarget.disabled = true;
        try {
            if (this.toggleTarget.checked) {
                await this.subscribe();
            } else {
                await this.unsubscribe();
            }
            this.hideHint();
        } catch {
            this.toggleTarget.checked = !this.toggleTarget.checked;
            this.showHint(Notification.permission === 'denied' ? this.textDeniedValue : this.textErrorValue);
        } finally {
            this.toggleTarget.disabled = Notification.permission === 'denied';
            this.updateFrequencyRow();
        }
    }

    async changeFrequency() {
        if (this.toggleTarget.checked) {
            try {
                await this.subscribe();
            } catch {
                this.showHint(this.textErrorValue);
            }
        }
    }

    async subscribe() {
        if ((await Notification.requestPermission()) !== 'granted') {
            throw new Error('Keine Erlaubnis');
        }
        const subscription = (await this.registration.pushManager.getSubscription())
            ?? await this.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: base64UrlToUint8Array(this.publicKey) });
        const { endpoint, keys } = subscription.toJSON();
        await this.api('POST', this.subscriptionUrlValue, { endpoint, p256dh: keys.p256dh, auth: keys.auth, frequency: this.frequencyTarget.value });
    }

    async unsubscribe() {
        const subscription = await this.registration.pushManager.getSubscription();
        if (subscription) {
            await this.api('DELETE', this.subscriptionUrlValue, { endpoint: subscription.endpoint });
            await subscription.unsubscribe();
        }
    }

    async api(method, url, body = undefined) {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'mesia', ...(body ? { 'Content-Type': 'application/json' } : {}) },
            body: body ? JSON.stringify(body) : undefined,
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.status === 204 ? null : response.json();
    }

    updateFrequencyRow() {
        this.frequencyRowTarget.hidden = !this.toggleTarget.checked;
    }

    showHint(text) {
        this.hintTarget.textContent = text;
        this.hintTarget.hidden = false;
    }

    hideHint() {
        this.hintTarget.hidden = true;
    }
}

/** VAPID-Schlüssel (Base64URL) → Uint8Array für pushManager.subscribe() */
function base64UrlToUint8Array(value) {
    const base64 = (value + '='.repeat((4 - (value.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');

    return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
}
