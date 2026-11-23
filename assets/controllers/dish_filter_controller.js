import { Controller } from '@hotwired/stimulus';

/**
 * Tab „Gerichte“: Suche und Schlagwort-Filter direkt im Browser, ohne Server (die Liste ist klein).
 * Suche ohne Groß-/Kleinschreibung und Akzente ("lasagne" findet "Lasagne", "creme" findet "Crème").
 * Mehrere Schlagworte: Gerichte, die alle haben.
 */
export default class extends Controller {
    static targets = ['query', 'tag', 'item', 'empty'];

    connect() {
        this.active = new Set();
        this.searchable = new Map(this.itemTargets.map((item) => [item, fold(item.dataset.search ?? '')]));
    }

    toggleTag(event) {
        const button = event.currentTarget;
        const tag = button.dataset.tag;
        if (this.active.has(tag)) {
            this.active.delete(tag);
        } else {
            this.active.add(tag);
        }
        button.setAttribute('aria-pressed', String(this.active.has(tag)));
        this.filter();
    }

    filter() {
        const words = fold(this.queryTarget.value).split(' ').filter(Boolean);
        let visible = 0;
        for (const item of this.itemTargets) {
            const tags = (item.dataset.tags ?? '').split('|');
            const matches = words.every((word) => this.searchable.get(item).includes(word))
                && [...this.active].every((tag) => tags.includes(tag));
            item.hidden = !matches;
            visible += matches ? 1 : 0;
        }
        this.emptyTarget.hidden = visible > 0;
    }
}

function fold(text) {
    return text.toLocaleLowerCase('de').normalize('NFD').replace(/\p{Diacritic}/gu, '').replace(/\s+/g, ' ').trim();
}
