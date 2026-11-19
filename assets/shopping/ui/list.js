import Sortable from 'sortablejs';
import { buildListView, formatNumber } from '../core/view.js';
import { addArticle, addFromText, checkItem, createContext, deleteItem, editItem, moveItem, reactivateItem, uncheckItem } from '../core/commands.js';
import { suggest } from '../core/matcher.js';
import { parseQuickAdd } from '../core/quickadd.js';
import { UNITS } from '../core/units.js';

const TOAST_MS = 4000;

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
    // Wurzelelement, in init() gesetzt. Alpine-Magics wie $refs/$root sind nach einem await nicht mehr verfügbar,
    // deshalb greifen die Methoden über dieses Element auf das DOM zu.
    let root = null;
    const element = (selector) => root?.querySelector(selector) ?? null;

    return {
        ready: false,
        hasData: false,
        view: { listId: null, categories: [], groups: [], openCount: 0, done: [] },
        query: '',
        suggestions: [],
        showDone: false,
        toast: null,
        editor: null,
        units: Object.entries(UNITS).map(([value, unit]) => ({ value, label: unit.label })),

        async init() {
            root = this.$el;
            cleanups.push(engine.subscribe(() => this.render()));
            cleanups.push(trackKeyboardInset());
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
        },

        render() {
            this.hasData = engine.hasData;
            this.view = buildListView(engine.state);

            const rejected = engine.takeRejected();
            if (rejected.length > 0) {
                this.showToast(rejected[0].error);
            }
        },

        async run(mutations) {
            try {
                await engine.execute(mutations);
            } catch (error) {
                this.showToast(error.message);
            }
        },

        // ------------------------------------------------------------ Hinzufügen

        async add() {
            const text = this.query;
            if (!text.trim()) {
                return;
            }
            this.query = '';
            this.suggestions = [];
            await this.run(addFromText(engine.state, text, ctx));
            element('.quick-add__input')?.focus();
        },

        async pick(suggestion) {
            const parsed = parseQuickAdd(this.query);
            this.query = '';
            this.suggestions = [];
            await this.run(addArticle(engine.state, suggestion.article, { ...parsed, name: suggestion.article.name }, ctx));
            element('.quick-add__input')?.focus();
        },

        updateSuggestions() {
            this.suggestions = suggest(engine.state, parseQuickAdd(this.query).name).map((s) => ({
                id: s.article.id,
                name: s.article.name,
                fromHistory: s.fromHistory,
                onList: s.onList,
                article: s.article,
            }));
        },

        // ------------------------------------------------------------ Abhaken

        async check(item) {
            await this.run(checkItem(item.id, ctx));
            this.showToast(root.dataset.textChecked.replace('%name%', item.name), item.id);
        },

        async undoCheck() {
            const itemId = this.toast?.undoItemId;
            this.hideToast();
            if (itemId) {
                await this.run(uncheckItem(itemId, ctx));
            }
        },

        async reactivate(item) {
            await this.run(reactivateItem(item.id, ctx));
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
                quantity: item.editableQuantity.quantity === null ? '' : formatNumber(item.editableQuantity.quantity),
                unit: item.editableQuantity.unit ?? '',
                categoryId: source.category_id,
                original: item.editableQuantity,
            };
            element('dialog.sheet').showModal();
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

            const quantity = parseNumber(form.quantity);
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

        showToast(text, undoItemId = null) {
            clearTimeout(toastTimer);
            this.toast = { text, undoItemId };
            toastTimer = setTimeout(() => this.hideToast(), TOAST_MS);
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

function parseNumber(value) {
    const number = Number.parseFloat(String(value).trim().replace(',', '.'));

    return Number.isFinite(number) && number > 0 ? number : null;
}

/**
 * Hält die Eingabeleiste über der Bildschirmtastatur (iOS verschiebt fixierte Elemente sonst nicht mit).
 */
function trackKeyboardInset() {
    const viewport = window.visualViewport;
    if (!viewport) {
        return () => {};
    }
    const update = () => {
        const inset = Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop);
        document.documentElement.style.setProperty('--keyboard-inset', `${Math.round(inset)}px`);
    };
    viewport.addEventListener('resize', update);
    viewport.addEventListener('scroll', update);
    update();

    return () => {
        viewport.removeEventListener('resize', update);
        viewport.removeEventListener('scroll', update);
        document.documentElement.style.removeProperty('--keyboard-inset');
    };
}
