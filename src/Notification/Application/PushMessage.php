<?php

declare(strict_types=1);

namespace App\Notification\Application;

/**
 * Inhalt einer Benachrichtigung. "tag" sorgt dafür, dass eine neue Nachricht die vorige ersetzt statt zu stapeln.
 */
final readonly class PushMessage
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url = '/einkauf',
        public string $tag = 'mesia-einkauf',
    ) {
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'tag' => $this->tag];
    }
}
