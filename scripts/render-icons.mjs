/**
 * Erzeugt die PNG-Icons und favicon.ico aus den SVG-Quellen in public/icons/.
 *
 * Gerendert wird mit Chromium (Playwright), weil die Bildmarke Konturen nutzt, die ImageMagick nicht darstellt.
 * Aufruf im DDEV-Container: ddev exec node scripts/render-icons.mjs
 * (Playwright-Browser einmalig installieren, siehe docs/entwicklung.md, Abschnitt Tests)
 */
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const icons = resolve('public/icons');
const jobs = [
    { src: 'icon.svg', out: 'icon-192.png', size: 192 },
    { src: 'icon.svg', out: 'icon-512.png', size: 512 },
    { src: 'icon-maskable.svg', out: 'icon-maskable-512.png', size: 512 },
    { src: 'icon-maskable.svg', out: 'apple-touch-icon.png', size: 180 }, // randlos, iOS rundet selbst ab
];
const faviconSizes = [16, 32, 48];

const browser = await chromium.launch();
const page = await browser.newPage();

async function render(src, out, size) {
    await page.setViewportSize({ width: size, height: size });
    await page.goto(`file://${join(icons, src)}`);
    await page.screenshot({ path: out, omitBackground: true });
}

for (const { src, out, size } of jobs) {
    await render(src, join(icons, out), size);
    console.log(`✓ ${out} (${size} px)`);
}

const tmp = mkdtempSync(join(tmpdir(), 'favicon-'));
const parts = [];
for (const size of faviconSizes) {
    const file = join(tmp, `favicon-${size}.png`);
    await render('favicon.svg', file, size);
    parts.push(file);
}
execFileSync('magick', [...parts, resolve('public/favicon.ico')]);
rmSync(tmp, { recursive: true });
console.log(`✓ favicon.ico (${faviconSizes.join(', ')} px)`);

await browser.close();
