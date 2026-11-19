/**
 * Rauchtest der Einkaufsliste im echten Browser (Playwright, Chromium, iPhone-Viewport).
 *
 * Aufruf (im DDEV-Container, gegen die laufende Dev-App):
 *   ddev exec node tests/e2e/smoke.mjs <email> <passwort> [screenshot-verzeichnis]
 *
 * Der Benutzer sollte zu einem eigenen Test-Haushalt gehören, der Test legt Einträge an.
 */
import { chromium, devices } from 'playwright';

const [email, password, screenshotDir = 'var/e2e'] = process.argv.slice(2);
const BASE = process.env.E2E_BASE_URL ?? 'http://localhost';
const errors = [];
const steps = [];

function step(name) {
    steps.push(name);
    console.log(`→ ${name}`);
}

async function expectVisible(locator, name) {
    await locator.first().waitFor({ state: 'visible', timeout: 10000 }).catch(() => {
        throw new Error(`Nicht sichtbar: ${name}`);
    });
}

async function waitForSync(page, expected = 'synced') {
    await page.waitForFunction((state) => window.Alpine?.store('sync')?.state === state, expected, { timeout: 15000 });
}

const browser = await chromium.launch();
const context = await browser.newContext({ ...devices['iPhone 13'], locale: 'de-DE' });
// Die Symfony-Debug-Toolbar (nur dev) liegt über der Tab-Leiste und fängt Klicks ab
await context.addInitScript(() => {
    document.addEventListener('DOMContentLoaded', () => {
        const style = document.createElement('style');
        style.textContent = '.sf-toolbar, .sf-minitoolbar { display: none !important; }';
        document.head.append(style);
    });
});
const page = await context.newPage();
page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
page.on('console', (message) => {
    // Fehlgeschlagene Requests im Offline-Schritt sind erwartet
    if (message.type() === 'error' && !message.text().includes('ERR_INTERNET_DISCONNECTED')) {
        errors.push(`console: ${message.text()}`);
    }
});

try {
    step('Login');
    await page.goto(`${BASE}/login`);
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', password);
    await page.click('button[type=submit]');
    await page.waitForURL('**/einkauf');

    step('Erster Sync (Snapshot)');
    await waitForSync(page);
    await page.screenshot({ path: `${screenshotDir}/0-start.png` });
    // Hinweis "Zum Home-Bildschirm" (erscheint im iPhone-Safari) wie ein Benutzer ausblenden
    const hint = page.locator('.install-hint:not([hidden])');
    if (await hint.count()) {
        await hint.locator('button').click();
    }
    await expectVisible(page.locator('.quick-add__input:not([disabled])'), 'Eingabefeld');

    step('Schnelleingabe "2 l Milch"');
    await page.fill('.quick-add__input', '2 l Milch');
    await page.press('.quick-add__input', 'Enter');
    const milk = page.locator('.shopping-group', { hasText: 'Milchprodukte' }).locator('.item-row', { hasText: 'Milch' });
    await expectVisible(milk, 'Milch in Milchprodukte');
    if (!(await milk.locator('.item-qty').innerText()).includes('2 l')) throw new Error('Menge "2 l" fehlt');

    step('Unbekannter Artikel landet in "Sonstiges"');
    await page.fill('.quick-add__input', 'drachenfrucht');
    await page.press('.quick-add__input', 'Enter');
    await expectVisible(page.locator('.shopping-group', { hasText: 'Sonstiges' }).locator('.item-row', { hasText: 'Drachenfrucht' }), 'Drachenfrucht in Sonstiges');

    step('Vorschläge beim Tippen');
    await page.fill('.quick-add__input', 'But');
    await page.dispatchEvent('.quick-add__input', 'input');
    await expectVisible(page.locator('.quick-add__suggestion', { hasText: 'Butter' }), 'Vorschlag Butter');
    await page.locator('.quick-add__suggestion', { hasText: 'Butter' }).first().click();
    await expectVisible(page.locator('.item-row', { hasText: 'Butter' }), 'Butter auf der Liste');

    step('Sync nach Änderungen');
    await waitForSync(page);
    await page.screenshot({ path: `${screenshotDir}/1-liste.png`, fullPage: true });

    step('Abhaken mit Rückgängig-Hinweis');
    await milk.locator('.item-check').click();
    await expectVisible(page.locator('.toast', { hasText: 'erledigt' }), 'Toast');
    await expectVisible(page.locator('.shopping-done__toggle', { hasText: 'Erledigt (1)' }), 'Erledigt (1)');
    await page.screenshot({ path: `${screenshotDir}/2-abgehakt.png`, fullPage: true });

    step('Bearbeiten: Drachenfrucht nach Obst & Gemüse');
    await page.locator('.item-row', { hasText: 'Drachenfrucht' }).locator('.item-body').click();
    await expectVisible(page.locator('dialog.sheet[open]'), 'Sheet');
    const produceValue = await page.locator('dialog.sheet select').nth(1).locator('option', { hasText: 'Obst & Gemüse' }).getAttribute('value');
    await page.locator('dialog.sheet select').nth(1).selectOption(produceValue);
    await page.fill('dialog.sheet input[inputmode=decimal]', '3');
    await page.waitForTimeout(400); // Öffnen-Animation abwarten
    await page.screenshot({ path: `${screenshotDir}/3-bearbeiten.png` });
    await page.click('dialog.sheet button[type=submit]');
    const dragonFruit = page.locator('.shopping-group', { hasText: 'Obst' }).locator('.item-row', { hasText: 'Drachenfrucht' });
    await expectVisible(dragonFruit, 'Drachenfrucht in Obst & Gemüse');
    await waitForSync(page);

    step('Offline hinzufügen, danach synchronisieren');
    await context.setOffline(true);
    await page.fill('.quick-add__input', 'Brot');
    await page.press('.quick-add__input', 'Enter');
    await expectVisible(page.locator('.item-row--pending', { hasText: 'Brot' }), 'Brot (ausstehend)');
    await waitForSync(page, 'offline');
    await page.screenshot({ path: `${screenshotDir}/4-offline.png`, fullPage: true });
    await context.setOffline(false);
    await page.evaluate(() => window.dispatchEvent(new Event('online')));
    await waitForSync(page);

    step('Neu laden: Daten bleiben erhalten');
    await page.reload();
    await waitForSync(page);
    for (const name of ['Brot', 'Butter', 'Drachenfrucht']) {
        await expectVisible(page.locator('.item-row:not(.item-row--done)', { hasText: name }), name);
    }

    step('Reaktivieren aus "Erledigt"');
    await page.click('.shopping-done__toggle');
    await page.locator('.item-row--done', { hasText: 'Milch' }).locator('.item-check').click();
    await expectVisible(page.locator('.shopping-group', { hasText: 'Milchprodukte' }).locator('.item-row', { hasText: 'Milch' }), 'Milch wieder offen');
    await waitForSync(page);

    step('Tab-Wechsel per Turbo und zurück');
    await page.click('nav.tabbar a[href="/mehr"]');
    await page.waitForURL('**/mehr');
    await page.click('nav.tabbar a[href="/einkauf"]');
    await page.waitForURL('**/einkauf');
    await expectVisible(page.locator('.item-row', { hasText: 'Brot' }), 'Liste nach Tab-Wechsel');
    if ((await page.locator('.item-row', { hasText: 'Brot' }).count()) !== 1) throw new Error('Brot doppelt nach Tab-Wechsel');
    await page.screenshot({ path: `${screenshotDir}/5-final.png`, fullPage: true });

    if (errors.length > 0) {
        throw new Error(`Browserfehler:\n${errors.join('\n')}`);
    }
    console.log(`\n✓ ${steps.length} Schritte erfolgreich`);
} catch (error) {
    await page.screenshot({ path: `${screenshotDir}/fehler.png`, fullPage: true }).catch(() => {});
    console.error(`\n✗ Fehlgeschlagen bei "${steps.at(-1)}": ${error.message}`);
    if (errors.length) console.error(errors.join('\n'));
    process.exitCode = 1;
} finally {
    await browser.close();
}
