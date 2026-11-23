<?php

declare(strict_types=1);

namespace App\Notification\Application;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Formuliert die Sammel-Nachricht: "Anna hat Milch, Brot und 2 weitere auf die Liste gesetzt.".
 */
final readonly class NotificationText
{
    private const int NAMED_ITEMS = 3;

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @param non-empty-list<AddedItem> $items
     */
    public function compose(array $items): PushMessage
    {
        $names = array_values(array_unique(array_map(static fn (AddedItem $item): string => $item->name, $items)));
        $actors = array_values(array_unique(array_map(fn (AddedItem $item): string => $item->actorName ?? $this->translator->trans('push.someone'), $items)));

        $shown = \array_slice($names, 0, self::NAMED_ITEMS);
        $rest = \count($names) - \count($shown);
        if ($rest > 0) {
            $shown[] = $this->translator->trans('push.more', ['%count%' => $rest]);
        }

        return new PushMessage(
            title: $this->translator->trans('push.title'),
            body: $this->translator->trans(1 === \count($actors) ? 'push.body_one' : 'push.body_many', [
                '%actors%' => $this->enumerate($actors),
                '%items%' => $this->enumerate($shown),
            ]),
        );
    }

    /**
     * "A", "A und B", "A, B und C".
     *
     * @param list<string> $parts
     */
    private function enumerate(array $parts): string
    {
        if (\count($parts) <= 1) {
            return $parts[0] ?? '';
        }
        $last = array_pop($parts);

        return implode(', ', $parts).' '.$this->translator->trans('push.and').' '.$last;
    }
}
