<?php

declare(strict_types=1);

namespace App\Notification\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class EndpointRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 1000)]
        public string $endpoint,
    ) {
    }
}
