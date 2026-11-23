<?php

declare(strict_types=1);

namespace App\Notification\UI\Http;

use App\Notification\Domain\PushFrequency;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Abo aus PushSubscription.toJSON() des Browsers plus gewünschte Häufigkeit.
 */
final readonly class SubscribeRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Url(requireTld: true), Assert\Length(max: 1000)]
        public string $endpoint,
        #[Assert\NotBlank, Assert\Length(max: 200)]
        public string $p256dh,
        #[Assert\NotBlank, Assert\Length(max: 100)]
        public string $auth,
        public PushFrequency $frequency = PushFrequency::Immediate,
    ) {
    }
}
