/**
 * Registriert den Service Worker (/sw.js, gerendert von ServiceWorkerController).
 *
 * Er liegt im Web-Root und nicht unter /assets/: Der Scope eines Service Workers ist auf sein Verzeichnis begrenzt,
 * und die URL muss stabil bleiben (AssetMapper versioniert Dateinamen).
 *
 * Neue Version: Der Browser prüft /sw.js bei echten Seitenaufrufen. Turbo-Navigationen sind keine, und eine
 * Home-Bildschirm-App bleibt oft tagelang offen. Deshalb wird zusätzlich beim Zurückkehren in die App geprüft.
 * Übernimmt ein neuer Service Worker, erhält <html> das Attribut data-update-available und das Ereignis
 * "mealplanner:update-available" wird ausgelöst (Hinweis "Neue Version", controllers/app_update_controller.js).
 */
export const UPDATE_EVENT = 'mealplanner:update-available';

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    // Beim allerersten Besuch übernimmt der Service Worker die Seite ebenfalls. Das ist kein Update.
    const hadController = navigator.serviceWorker.controller !== null;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (hadController) {
            document.documentElement.dataset.updateAvailable = 'true';
            window.dispatchEvent(new CustomEvent(UPDATE_EVENT));
        }
    });

    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    registration.update().catch(() => {});
                }
            });
        } catch (error) {
            console.warn('Service Worker konnte nicht registriert werden:', error);
        }
    });
}
