<?php

declare(strict_types=1);

namespace App\Shared\Application\Sync;

use Symfony\Component\Uid\Uuid;

/**
 * Ergebnis einer erfolgreich verarbeiteten Mutation. Fachliche Ablehnungen werden als {@see MutationRejected} geworfen.
 */
final readonly class MutationOutcome
{
    private function __construct(
        public string $status,
        public ?Uuid $mergedInto = null,
    ) {
    }

    public static function applied(): self
    {
        return new self('applied');
    }

    /**
     * Die Mutation wurde auf ein bestehendes Objekt umgelenkt (z. B. zwei Geräte legen offline denselben Artikel an).
     */
    public static function merged(Uuid $into): self
    {
        return new self('merged', $into);
    }

    /**
     * Veraltet und deshalb ohne Wirkung (z. B. Abhaken, das älter ist als ein späteres Reaktivieren).
     */
    public static function ignored(): self
    {
        return new self('ignored');
    }
}
