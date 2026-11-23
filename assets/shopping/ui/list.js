import Sortable from 'sortablejs';
import { buildListView, formatInputNumber, formatNumber } from '../core/view.js';
import { TextTooLongError, addArticle, addFromText, checkItem, createContext, deleteItem, editItem, moveItem, reactivateItem, uncheckItem } from '../core/commands.js';
import { suggest } from '../core/matcher.js';
import { LENGTH_WARNING_AT, NAME_MAX_LENGTH, NOTE_MAX_LENGTH, parseQuickAdd, textLength } from '../core/quickadd.js';
import { UNITS } from '../core/units.js';
import { activeList, findItemByArticle } from '../core/state.js';
import { QuantityError, parseQuantity } from '../core/quantity.js';
import { statusLine } from '../core/status.js';

const TOAST_MS = 4000;
/**
 * Abgehakte Einträge bleiben an ihrem Platz, bis so lange nichts mehr abgehakt wurde. Dann wandern alle gemeinsam
 * nach „Erledigt“. So rutscht beim Abhaken im Laden keine Zeile unter dem Finger weg.
 */
const KEEP_IN_PLACE_MS = 1000;
/** Direkt nach dem Zusammenrücken zählt ein Tipp nicht (er galt vermutlich der Zeile, die vorher dort stand) */
const SETTLE_GUARD_MS = 400;
/** Zweiter Tipp auf denselben Kreis so kurz danach: versehentlicher Doppeltipp, kein Rückgängig */
const DOUBLE_TAP_MS = 500;
/** Fehler und Ablehnungen etwas länger, damit man sie lesen kann */
const ERROR_TOAST_MS = 6000;

/**
 * Alpine-Komponente der Einkaufsliste. Die Logik liegt in ../core (getestet mit Vitest), hier wird nur verdrahtet.
 * Texte kommen aus dem Twig-Template (data-text-*), damit alle UI-Texte in translations/ bleiben.
 */
export function shoppingList(engine) {
    // Nicht reaktive Helfer (bewusst außerhalb des Alpine-Datenobjekts)
    const ctx = createContext();
    const sortables = [];
    const cleanups = [];
    let toastTimer = null;
    let dragOrigin = null;
    // Gerade abgehakt, noch an ihrem Platz (siehe KEEP_IN_PLACE_MS)
    const keptInPlace = new Set();
    let keepTimer = null;
    let settledAt = 0;
    let lastTap = { id: null, at: 0 };
    // Wurzelelement, in init() gesetzt. Alpine-Magics wie $refs/$root sind nach einem await nicht mehr verfügbar,
    // deshalb greifen die Methoden über dieses Element auf das DOM zu.
    let root = null;
    const element = (selector) => root?.querySelector(selector) ?? null;
    const text = (key) => root?.dataset[key] ?? '';

    // Hinweis zur Länge: ab LENGTH_WARNING_AT Zeichen "Noch x Zeichen", darüber "x Zeichen zu viel"
    const lengthHint = (length, max, overKey = 'textLengthOver') => {
        if (length > max) {
            return { over: true, text: text(overKey).replace('%count%', length - max).replace('%max%', max) };
        }
        if (length >= max - (NAME_MAX_LENGTH - LENGTH_WARNING_AT)) {
            return { over: false, text: text('textLengthRemaining').replace('%count%', max - length) };
        }

        return null;
    };

    // Verständliche Meldung zu einer unplausiblen Menge (Texte aus dem Twig-Template)
    const quantityMessage = (error) => {
        const key = { too_large: 'textQuantityTooLarge', not_positive: 'textQuantityNotPositive' }[error.reason] ?? 'textQuantityInvalid';

        return text(key).replace('%value%', Number.isFinite(error.value) ? formatNumber(error.value) : String(error.value));
    };

    // Gerade hinzugefügten Eintrag in den sichtbaren Teil der Liste scrollen (auch wenn er schon auf der Liste stand)
    const revealAddedItem = (mutations) => {
        const articleId = mutations.find((mutation) => mutation.type === 'item.add')?.payload.article_id;
        const list = activeList(engine.state);
        const item = articleId && list ? findItemByArticle(engine.state, list.id, articleId) : null;
        if (item) {
            // Alpine rendert asynchron: erst danach existiert die Zeile
            requestAnimationFrame(() => element(`[data-id="${item.id}"]`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
        }
    };

    return {
        ready: false,
        hasData: false,
        view: { listId: null, categories: [], groups: [], openCount: 0, done: [] },
        query: '',
        suggestions: [],
        /** Längenhinweis zur Schnelleingabe ({over, text}) oder null */
        lengthHint: null,
        showDone: false,
        toast: null,
        editor: null,
        units: Object.entries(UNITS).map(([value, unit]) => ({ value, label: unit.label })),

        async init() {
            root = this.$el;
            // Vorschläge und Längenhinweis immer zum aktuellen Text (ein @input im Template liefe vor x-model,
            // also einen Tastendruck zu spät)
            this.$watch('query', () => this.updateSuggestions());
            cleanups.push(engine.subscribe(() => this.render()));
            cleanups.push(trackVisualViewport());
            await engine.init();
            this.render();
            this.ready = true;
            engine.attach();
        },

        destroy() {
            engine.detach();
            cleanups.splice(0).forEach((cleanup) => cleanup());
            sortables.splice(0).forEach((sortable) => sortable.destroy());
            clearTimeout(toastTimer);
            clearTimeout(keepTimer);
        },

        render() {
            this.hasData = engine.hasData;
            this.view = buildListView(engine.state, { keepInPlace: keptInPlace });

            const [rejected] = engine.takeRejected();
            if (rejected) {
                const message = rejected.name
                    ? text('textRejected').replace('%name%', rejected.name).replace('%error%', rejected.error)
                    : rejected.error;
                this.showToast(message, { duration: ERROR_TOAST_MS, error: true });
            }
        },

        async run(mutations) {
            try {
                await engine.execute(mutations);
            } catch (error) {
                this.showToast(error.message, { duration: ERROR_TOAST_MS, error: true });
            }
        },

        /**
         * Mutationen erzeugen. Bei unplausibler Menge: Hinweis, nichts anlegen, Eingabe bleibt zum Korrigieren stehen.
         */
        prepare(create) {
            try {
                return create();
            } catch (error) {
                if (error instanceof TextTooLongError) {
                    this.showToast(lengthHint(error.length, error.max, error.field === 'note' ? 'textNoteOver' : 'textLengthOver').text, { duration: ERROR_TOAST_MS, error: true });

                    return null;
                }
                if (!(error instanceof QuantityError)) {
                    throw error;
                }
                this.showToast(quantityMessage(error), { duration: ERROR_TOAST_MS, error: true });

                return null;
            }
        },

        // ------------------------------------------------------------ Hinzufügen

        async add() {
            const input = this.query;
            if (!input.trim() || this.lengthHint?.over) {
                return;
            }
            const mutations = this.prepare(() => addFromText(engine.state, input, ctx));
            if (!mutations) {
                return;
            }
            this.query = '';
            this.suggestions = [];
            this.lengthHint = null;
            await this.run(mutations);
            element('.quick-add__input')?.focus();
            revealAddedItem(mutations);
        },

        async pick(suggestion) {
            const parsed = parseQuickAdd(this.query);
            const mutations = this.prepare(() => addArticle(engine.state, suggestion.article, { ...parsed, name: suggestion.article.name }, ctx));
            if (!mutations) {
                return;
            }
            this.query = '';
            this.suggestions = [];
            this.lengthHint = null;
            await this.run(mutations);
            element('.quick-add__input')?.focus();
            revealAddedItem(mutations);
        },

        updateSuggestions() {
            const parsed = parseQuickAdd(this.query);
            // Gezählt wird, was gespeichert wird: der Name ohne Menge, die Notiz in Klammern separat
            this.lengthHint = lengthHint(textLength(parsed.name), NAME_MAX_LENGTH)
                ?? (textLength(parsed.note) > NOTE_MAX_LENGTH ? lengthHint(textLength(parsed.note), NOTE_MAX_LENGTH, 'textNoteOver') : null);
            this.suggestions = suggest(engine.state, parsed.name).map((s) => ({
                id: s.article.id,
                name: s.article.name,
                fromHistory: s.fromHistory,
                onList: s.onList,
                article: s.article,
            }));
        },

        // ------------------------------------------------------------ Abhaken

        /**
         * Abhaken: Kreis füllt sich mit Häkchen, der Name wird durchgestrichen, die Zeile bleibt aber stehen. Erst wenn
         * eine Weile nichts mehr abgehakt wurde, wandern alle abgehakten Einträge gemeinsam nach „Erledigt“.
         * Gespeichert und abgeglichen wird sofort. Ein weiterer Tipp auf einen noch stehenden Eintrag macht es rückgängig.
         */
        async check(item) {
            const now = Date.now();
            if (now - settledAt < SETTLE_GUARD_MS || (lastTap.id === item.id && now - lastTap.at < DOUBLE_TAP_MS)) {
                return;
            }
            lastTap = { id: item.id, at: now };

            if (item.checked) {
                keptInPlace.delete(item.id);
                this.keepInPlaceAWhile();
                await this.run(uncheckItem(item.id, ctx));
                return;
            }
            keptInPlace.add(item.id);
            this.keepInPlaceAWhile();
            await this.run(checkItem(item.id, ctx));
        },

        /** Frist neu starten: Solange weiter abgehakt wird, bleibt alles stehen */
        keepInPlaceAWhile() {
            clearTimeout(keepTimer);
            if (keptInPlace.size === 0) {
                return;
            }
            keepTimer = setTimeout(() => {
                keptInPlace.clear();
                settledAt = Date.now();
                this.render();
            }, KEEP_IN_PLACE_MS);
        },

        async reactivate(item) {
            await this.run(reactivateItem(item.id, ctx));
        },

        /**
         * Aus „Erledigt“ entfernen (z. B. Einmalkäufe). Der Artikel bleibt im Katalog und wird beim nächsten
         * Eintippen wieder erkannt, die Kaufhistorie bleibt erhalten.
         */
        async removeDone(item) {
            await this.run(deleteItem(item.id, ctx));
        },

        // ------------------------------------------------------------ Bearbeiten

        openEditor(item) {
            const source = engine.state.items.get(item.id);
            if (!source) {
                return;
            }
            this.editor = {
                id: item.id,
                name: source.name,
                note: source.note ?? '',
                quantity: item.editableQuantity.quantity === null ? '' : formatInputNumber(item.editableQuantity.quantity),
                quantityError: null,
                unit: item.editableQuantity.unit ?? '',
                categoryId: source.category_id,
                original: item.editableQuantity,
                checked: item.checked,
                // "Hinzugefügt von Anna heute um 9:05", bei Abgehaktem zusätzlich "Erledigt von …"
                statusLines: this.statusLines(item),
            };
            element('dialog.sheet').showModal();
        },

        statusLines(item) {
            const options = { names: engine.people.names, texts: JSON.parse(text('textStatus') || '{}'), now: new Date() };

            return [item.status.added, item.status.checked]
                .filter(Boolean)
                .map((status) => statusLine(status, options))
                .filter((line) => line !== '');
        },

        /** Hinweis unter dem Namensfeld im Bearbeiten-Sheet (Feld selbst ist auf 100 Zeichen begrenzt) */
        editorNameHint() {
            return this.editor ? lengthHint(textLength(this.editor.name), NAME_MAX_LENGTH)?.text ?? '' : '';
        },

        closeEditor() {
            element('dialog.sheet')?.close();
            this.editor = null;
        },

        async saveEditor() {
            const form = this.editor;
            const item = engine.state.items.get(form.id);
            if (!item) {
                this.closeEditor();
                return;
            }

            let quantity;
            try {
                quantity = parseQuantity(form.quantity);
            } catch (error) {
                if (!(error instanceof QuantityError)) {
                    throw error;
                }
                // Das Sheet liegt über allem (auch über dem Toast): Hinweis direkt am Feld, Sheet bleibt offen
                form.quantityError = quantityMessage(error);
                return;
            }
            const mutations = editItem(engine.state, item, {
                name: form.name,
                note: form.note,
                quantity,
                unit: quantity === null ? null : (form.unit || null),
                categoryId: form.categoryId,
            }, form.original, ctx);

            this.closeEditor();
            await this.run(mutations);
        },

        /**
         * Abgehakten Eintrag aus dem Dialog zurückholen. Steht er noch an seinem Platz, ist es ein Rückgängig (Menge
         * bleibt), sonst ein Reaktivieren wie per Kreis in „Erledigt“.
         */
        async reactivateFromEditor() {
            const itemId = this.editor.id;
            this.closeEditor();
            if (keptInPlace.delete(itemId)) {
                this.keepInPlaceAWhile();
                await this.run(uncheckItem(itemId, ctx));
                return;
            }
            await this.run(reactivateItem(itemId, ctx));
        },

        async deleteFromEditor() {
            const itemId = this.editor.id;
            this.closeEditor();
            await this.run(deleteItem(itemId, ctx));
        },

        // ------------------------------------------------------------ Sortieren (lang drücken + ziehen)

        enableSorting(element) {
            sortables.push(Sortable.create(element, {
                group: 'shopping-items',
                draggable: '.item-row',
                delay: 300,
                delayOnTouchOnly: true,
                animation: 150,
                ghostClass: 'item-row--ghost',
                chosenClass: 'item-row--chosen',
                onStart: (event) => {
                    dragOrigin = { parent: event.from, next: event.item.nextSibling };
                },
                onEnd: (event) => {
                    const itemId = event.item.dataset.id;
                    const categoryId = event.to.dataset.categoryId;
                    const previousId = siblingRow(event.item, 'previousElementSibling')?.dataset.id ?? null;
                    const nextId = siblingRow(event.item, 'nextElementSibling')?.dataset.id ?? null;

                    // DOM zurücksetzen: Die Reihenfolge bestimmt der Zustand, Alpine rendert danach neu.
                    dragOrigin.parent.insertBefore(event.item, dragOrigin.next);

                    if (event.from !== event.to || event.oldDraggableIndex !== event.newDraggableIndex) {
                        this.run(moveItem(engine.state, itemId, categoryId, previousId, nextId, ctx));
                    }
                },
            }));
        },

        // ------------------------------------------------------------ Hinweise

        showToast(message, { duration = TOAST_MS, error = false } = {}) {
            clearTimeout(toastTimer);
            this.toast = { text: message, error };
            toastTimer = setTimeout(() => this.hideToast(), duration);
        },

        hideToast() {
            clearTimeout(toastTimer);
            this.toast = null;
        },
    };
}

function siblingRow(element, direction) {
    let sibling = element[direction];
    while (sibling && !sibling.classList.contains('item-row')) {
        sibling = sibling[direction];
    }

    return sibling;
}

/**
 * Die Listenseite ist eine feste Hülle in Größe des sichtbaren Bereichs (CSS: .page-shopping .app).
 *
 * iOS verkleinert beim Öffnen der Tastatur nur den sichtbaren Bereich (visualViewport) und schiebt ihn über das
 * Layout, um das Eingabefeld zu zeigen. Die Hülle folgt dem: Höhe = sichtbare Höhe, verschoben um offsetTop. So sitzt
 * die Eingabeleiste direkt über der Tastatur und nur die Liste darüber scrollt.
 */
function trackVisualViewport() {
    const viewport = window.visualViewport;
    const root = document.documentElement;
    if (!viewport) {
        return () => {};
    }
    const update = () => {
        root.style.setProperty('--vv-height', `${Math.round(viewport.height)}px`);
        root.style.setProperty('--vv-offset', `${Math.round(viewport.offsetTop)}px`);
        // iOS lässt das Layout beim Öffnen der Tastatur gleich groß, nur der sichtbare Bereich schrumpft
        root.dataset.keyboard = root.clientHeight - viewport.height > 120 ? 'open' : 'closed';
    };
    viewport.addEventListener('resize', update);
    viewport.addEventListener('scroll', update);
    update();

    return () => {
        viewport.removeEventListener('resize', update);
        viewport.removeEventListener('scroll', update);
        root.style.removeProperty('--vv-height');
        root.style.removeProperty('--vv-offset');
        delete root.dataset.keyboard;
    };
}
