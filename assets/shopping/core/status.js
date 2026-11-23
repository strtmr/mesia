/**
 * Statuszeile unter einem Eintrag: „Hinzugefügt von Anna heute um 9:05“, „Erledigt von Marco am 20.11. um 18:30“.
 * Texte kommen aus dem Twig-Template (translations: shopping.status.*), damit sie übersetzbar bleiben.
 */

const timeFormat = new Intl.DateTimeFormat('de-DE', { hour: 'numeric', minute: '2-digit' });
const dateFormat = new Intl.DateTimeFormat('de-DE', { day: 'numeric', month: 'numeric' });
const dateWithYearFormat = new Intl.DateTimeFormat('de-DE', { day: 'numeric', month: 'numeric', year: 'numeric' });

/**
 * @param {{kind: 'added'|'checked', userIds: string[], at: string|null}} status aus view.itemStatus()
 * @param {{names: Map<string, string>, texts: Record<string, string>, now?: Date}} options
 *        texts: added, added_anonymous, checked, checked_anonymous, today, yesterday, date, and
 * @returns {string} leer, wenn der Zeitpunkt fehlt
 */
export function statusLine(status, { names, texts, now = new Date() }) {
    if (!status?.at) {
        return '';
    }
    const at = new Date(status.at);
    if (Number.isNaN(at.getTime())) {
        return '';
    }

    // Unbekannte Benutzer (z. B. gelöschtes Konto) weglassen
    const known = status.userIds.map((id) => names.get(id)).filter(Boolean);
    const key = known.length > 0 ? status.kind : `${status.kind}_anonymous`;

    return (texts[key] ?? '')
        .replace('%name%', joinNames(known, texts.and ?? 'und'))
        .replace('%when%', when(at, now, texts));
}

/** "heute um 9:05", "gestern um 18:30", "am 20.11. um 7:15" (anderes Jahr: "am 20.11.2025 um 7:15") */
export function when(at, now, texts) {
    const days = calendarDaysBetween(at, now);
    const time = timeFormat.format(at);
    if (days === 0) {
        return texts.today.replace('%time%', time);
    }
    if (days === 1) {
        return texts.yesterday.replace('%time%', time);
    }
    const date = (at.getFullYear() === now.getFullYear() ? dateFormat : dateWithYearFormat).format(at);

    return texts.date.replace('%date%', date).replace('%time%', time);
}

/** Kalendertage (Ortszeit), nicht 24-Stunden-Blöcke: 23:50 gestern ist "gestern", auch wenn erst 10 Minuten her */
function calendarDaysBetween(earlier, later) {
    const startOfDay = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();

    return Math.round((startOfDay(later) - startOfDay(earlier)) / 86400000);
}

function joinNames(names, and) {
    return names.length <= 1 ? (names[0] ?? '') : `${names.slice(0, -1).join(', ')} ${and} ${names[names.length - 1]}`;
}
