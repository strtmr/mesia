<?php

declare(strict_types=1);

namespace App\Admin\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Filter der Seite „Oft gekauft“ (?zeitraum=90&haushalt=<ID>).
 */
final readonly class PurchaseFilterRequest
{
    public const array PERIODS = ['30', '90', '365', 'alle'];

    public function __construct(
        #[Assert\Choice(choices: self::PERIODS)]
        public string $zeitraum = '90',
        #[Assert\Uuid]
        public ?string $haushalt = null,
    ) {
    }
}
