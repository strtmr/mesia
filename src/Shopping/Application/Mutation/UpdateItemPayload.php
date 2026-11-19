<?php

declare(strict_types=1);

namespace App\Shopping\Application\Mutation;

use Symfony\Component\Uid\Uuid;

/**
 * "fields" enthält nur die geänderten Felder: name, note, qty_override, unit_override.
 * Ein Feld mit Wert null wird geleert. Ein fehlendes Feld bleibt unverändert.
 * Deshalb ein Array statt einzelner Properties (null und "nicht gesendet" wären sonst nicht unterscheidbar).
 */
final readonly class UpdateItemPayload
{
    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(
        public Uuid $itemId,
        public array $fields,
    ) {
    }
}
