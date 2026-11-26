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

// Farben aus dem Brand Book (Kapitel 5): Hintergrund Paper / Ink, Bildmarke Deep / Cream, Wortmarke Ink / Cream.
const THEMES = {
    light: { bg: '#f7f5ef', mark: '#124559', text: '#01161e' },
    dark: { bg: '#01161e', mark: '#eff6e0', text: '#eff6e0' },
};

// Als data:-URL eingebettet, weil eine per setContent geladene Seite keine file://-Schriften nachladen darf
const fontUrl = `data:font/woff2;base64,${readFileSync(resolve('assets/fonts/manrope-latin-wght.woff2')).toString('base64')}`;

/** Bildmarke (wie templates/app/_brand_mark.html.twig) und Wortmarke, mittig auf dem Hintergrund. */
function html(theme) {
    const { bg, mark, text } = THEMES[theme];

    return `<!doctype html><html><head><meta charset="utf-8"><style>
        @font-face { font-family: "Manrope"; font-weight: 200 800; src: url("${fontUrl}") format("woff2"); }
        html, body { margin: 0; height: 100%; background: ${bg}; }
        body { display: flex; flex-direction: column; align-items: center; justify-content: center; }
        /* etwas über der Mitte, wie es optisch ruhiger wirkt */
        main { display: flex; flex-direction: column; align-items: center; margin-bottom: 8vh; }
        /* Proportionen wie im Logo: Wortmarke etwa 1,75-mal so breit wie die Bildmarke */
        svg { width: 36vw; height: auto; }
        h1 { margin: 8vw 0 0; color: ${text}; font: 400 12vw/1 "Manrope"; letter-spacing: 0.28em; text-indent: 0.28em; text-transform: uppercase; }
    </style></head><body><main>
        <svg viewBox="0 0 370 290">
            <path d="M82 2 H288 A103 65 0 0 1 82 2 Z" fill="#ff6a2b"/>
            <g fill="none" stroke="${mark}" stroke-width="16" stroke-linecap="round" stroke-linejoin="round">
                <line x1="10" y1="92.5" x2="360" y2="92.5"/>
                <polyline points="45,279 45,129 185,227 325,129 325,279"/>
            </g>
        </svg>
        <h1>Mesuna</h1>
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
