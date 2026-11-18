/**
 * Registriert den Service Worker (public/sw.js).
 *
 * Er liegt bewusst im Web-Root und nicht im AssetMapper: Der Scope eines Service Workers ist auf sein Verzeichnis
 * begrenzt, und die URL muss stabil bleiben (AssetMapper versioniert Dateinamen).
 */
export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error) => {
            console.warn('Service Worker konnte nicht registriert werden:', error);
        });
    });
}
