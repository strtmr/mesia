/**
 * Erzeugt die Startbilder für iOS (apple-touch-startup-image) und das Twig-Partial, das sie einbindet.
 *
 * iOS ignoriert background_color aus dem Manifest und zeigt beim Start der installierten App ein weißes Bild, solange
 * kein Startbild für genau diese Bildschirmgröße hinterlegt ist. Deshalb ein Bild pro iPhone-Größe, hell und dunkel.
 *
 * Aufruf im DDEV-Container: ddev exec node scripts/render-splash.mjs
 * Ausgabe: public/splash/*.png und templates/pwa/_startup_images.html.twig (beides nicht von Hand ändern)
 * Neues iPhone mit neuer Bildschirmgröße: Zeile in DEVICES ergänzen und Skript erneut ausführen.
 */
import { chromium } from 'playwright';
import { mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';

// Bildschirm in CSS-Pixeln (Hochformat) und Pixeldichte
const DEVICES = [
    { width: 440, height: 956, ratio: 3, name: 'iPhone 16 Pro Max, 17 Pro Max' },
    { width: 420, height: 912, ratio: 3, name: 'iPhone Air' },
    { width: 402, height: 874, ratio: 3, name: 'iPhone 16 Pro, 17, 17 Pro' },
    { width: 430, height: 932, ratio: 3, name: 'iPhone 14 Pro Max, 15 Plus, 15 Pro Max, 16 Plus' },
    { width: 393, height: 852, ratio: 3, name: 'iPhone 14 Pro, 15, 15 Pro, 16' },
    { width: 428, height: 926, ratio: 3, name: 'iPhone 12 Pro Max, 13 Pro Max, 14 Plus' },
    { width: 390, height: 844, ratio: 3, name: 'iPhone 12, 12 Pro, 13, 13 Pro, 14, 16e' },
    { width: 375, height: 812, ratio: 3, name: 'iPhone X, XS, 11 Pro, 12 mini, 13 mini' },
    { width: 414, height: 896, ratio: 3, name: 'iPhone XS Max, 11 Pro Max' },
    { width: 414, height: 896, ratio: 2, name: 'iPhone XR, 11' },
    { width: 414, height: 736, ratio: 3, name: 'iPhone 8 Plus' },
    { width: 375, height: 667, ratio: 2, name: 'iPhone SE (2. und 3. Generation), 8' },
];

// Farben aus dem Brand Book (Kapitel 5): Hintergrund Paper / Ink, Wortmarke Ink / Cream.
// Versatz der Bildmarke in beiden Fällen Deep, wie auf der Login-Seite (auf Ink wäre ein Ink-Versatz unsichtbar).
const THEMES = {
    light: { bg: '#f7f5ef', text: '#01161e' },
    dark: { bg: '#01161e', text: '#eff6e0' },
};

const BOWL = 'M168 176 H344 C344 216 304 236 256 236 C208 236 168 216 168 176 Z';
const M = 'M124 428 V306 Q124 278 152 278 H200 Q210 278 216 286 L256 338 L296 286 Q302 278 312 278 H360 Q388 278 388 306 V428 H322 V364 L268 418 Q256 432 244 418 L190 364 V428 Z';

// Als data:-URL eingebettet, weil eine per setContent geladene Seite keine file://-Schriften nachladen darf
const fontUrl = `data:font/woff2;base64,${readFileSync(resolve('assets/fonts/archivo-black-latin.woff2')).toString('base64')}`;

/** Bildmarke (Stufe Sticker, wie templates/app/_brand_mark.html.twig) und Wortmarke, mittig auf dem Hintergrund. */
function html(theme) {
    const { bg, text } = THEMES[theme];

    return `<!doctype html><html><head><meta charset="utf-8"><style>
        @font-face { font-family: "Archivo Black"; src: url("${fontUrl}") format("woff2"); }
        html, body { margin: 0; height: 100%; background: ${bg}; }
        body { display: flex; flex-direction: column; align-items: center; justify-content: center; }
        /* etwas über der Mitte, wie es optisch ruhiger wirkt */
        main { display: flex; flex-direction: column; align-items: center; margin-bottom: 8vh; }
        svg { width: 46vw; height: auto; }
        h1 { margin: 5vw 0 0; color: ${text}; font: 400 13vw/1 "Archivo Black"; letter-spacing: 0.01em; }
    </style></head><body><main>
        <svg viewBox="70 140 380 330">
            <g transform="rotate(-5 256 300)" stroke-width="22" stroke-linejoin="round" paint-order="stroke">
                <g transform="translate(14 14)" fill="#124559" stroke="#124559">
                    <path d="${BOWL}"/><rect x="100" y="240" width="312" height="32" rx="16"/><path d="${M}"/>
                </g>
                <path d="${BOWL}" fill="#ff6a2b" stroke="#01161e"/>
                <rect x="100" y="240" width="312" height="32" rx="16" fill="#eff6e0" stroke="#01161e"/>
                <path d="${M}" fill="#eff6e0" stroke="#01161e"/>
            </g>
        </svg>
        <h1>MESIA</h1>
    </main></body></html>`;
}

function fileName({ width, height, ratio }, theme) {
    return `splash-${width * ratio}x${height * ratio}-${theme}.png`;
}

function media({ width, height, ratio }, theme) {
    const query = `screen and (device-width: ${width}px) and (device-height: ${height}px) and (-webkit-device-pixel-ratio: ${ratio}) and (orientation: portrait)`;

    return theme === 'dark' ? `${query} and (prefers-color-scheme: dark)` : query;
}

const outDir = resolve('public/splash');
mkdirSync(outDir, { recursive: true });
for (const file of readdirSync(outDir)) {
    if (file.endsWith('.png')) {
        rmSync(join(outDir, file));
    }
}

const browser = await chromium.launch();
const links = { light: [], dark: [] };

for (const device of DEVICES) {
    const page = await browser.newPage({
        viewport: { width: device.width, height: device.height },
        deviceScaleFactor: device.ratio,
    });
    for (const theme of Object.keys(THEMES)) {
        await page.setContent(html(theme));
        await page.evaluate(() => document.fonts.ready);
        const file = fileName(device, theme);
        await page.screenshot({ path: join(outDir, file) });
        links[theme].push(`{# ${device.name} #}\n<link rel="apple-touch-startup-image" media="${media(device, theme)}" href="/splash/${file}">`);
        console.log(`✓ ${file}`);
    }
    await page.close();
}

await browser.close();

// Dunkle Bilder zuerst, helle (ohne prefers-color-scheme) danach als Rückfall: So passt immer mindestens ein Bild,
// auch falls iOS die Farbschema-Abfrage einmal nicht auswertet.
const partial = `{#
    Startbilder für die installierte App auf dem iPhone (iOS ignoriert background_color aus dem Manifest).
    GENERIERT von scripts/render-splash.mjs, nicht von Hand ändern.
#}
${[...links.dark, ...links.light].join('\n')}
`;
writeFileSync(resolve('templates/pwa/_startup_images.html.twig'), partial);
console.log('✓ templates/pwa/_startup_images.html.twig');
