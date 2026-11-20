<?php

declare(strict_types=1);

namespace App\Catalog\Application;

final readonly class CatalogUpdateReport
{
    /**
     * @param list<string>                      $created Namen neu angelegter Artikel
     * @param list<array{0: string, 1: string}> $renamed [alter Name, neuer Name]
     */
    public function __construct(
        public array $created,
        public array $renamed,
        public int $aliasesAdded,
        public int $aliasesRemoved,
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->created && [] === $this->renamed && 0 === $this->aliasesAdded && 0 === $this->aliasesRemoved;
    }
}
