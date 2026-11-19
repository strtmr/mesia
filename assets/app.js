import './stimulus_bootstrap.js';
import './styles/app.css';
import { registerServiceWorker } from './pwa/register-service-worker.js';

registerServiceWorker();

// Die Offline-Einkaufsliste (Alpine, IndexedDB) nur laden, wenn die Seite sie enthält.
document.addEventListener('turbo:load', () => {
    if (document.querySelector('[data-shopping-app]')) {
        import('./shopping/ui/app.js');
    }
});
