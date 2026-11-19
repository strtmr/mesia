/**
 * Einheiten, identisch zu src/Catalog/Domain/Unit.php (Werte und Anzeigenamen).
 * "variants" sind die Schreibweisen, die die Schnelleingabe erkennt (normalisiert, ohne Punkt).
 */
export const UNITS = {
    g: { label: 'g', variants: ['g', 'gr', 'gramm'] },
    kg: { label: 'kg', variants: ['kg', 'kilo', 'kilogramm'] },
    ml: { label: 'ml', variants: ['ml', 'milliliter'] },
    l: { label: 'l', variants: ['l', 'liter', 'ltr'] },
    stk: { label: 'Stk', variants: ['stk', 'stueck', 'st'] },
    pck: { label: 'Pck', variants: ['pck', 'pckg', 'pkg', 'pack', 'packung', 'packungen', 'paeckchen'] },
    bund: { label: 'Bund', variants: ['bund', 'bd'] },
    dose: { label: 'Dose', variants: ['dose', 'dosen'] },
    flasche: { label: 'Fl.', variants: ['flasche', 'flaschen', 'fl'] },
    glas: { label: 'Glas', variants: ['glas', 'glaeser'] },
    becher: { label: 'Becher', variants: ['becher'] },
    el: { label: 'EL', variants: ['el', 'essloeffel'] },
    tl: { label: 'TL', variants: ['tl', 'teeloeffel'] },
    prise: { label: 'Prise', variants: ['prise', 'prisen'] },
};

const VARIANT_INDEX = new Map(
    Object.entries(UNITS).flatMap(([code, unit]) => unit.variants.map((variant) => [variant, code])),
);

/**
 * @param {string} token z. B. "Liter", "Stück", "Fl."
 * @returns {string|null} Einheitencode oder null
 */
export function unitFromToken(token) {
    const key = token
        .toLowerCase()
        .replace(/\.$/, '')
        .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss');

    return VARIANT_INDEX.get(key) ?? null;
}

export function unitLabel(code) {
    return code ? (UNITS[code]?.label ?? code) : '';
}
