<?php

declare(strict_types=1);

namespace App\Sync\UI\Http;

use App\Sync\Application\IncomingMutation;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body von POST /api/sync/push (JSON, snake_case).
 */
final readonly class PushRequest
{
    /**
     * @param list<IncomingMutation> $mutations
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $deviceId,
        #[Assert\Count(max: 200)]
        #[Assert\Valid]
        public array $mutations = [],
    ) {
    }
}
