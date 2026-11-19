<?php

declare(strict_types=1);

namespace App\Sync\Application;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Eine Mutation aus dem Push eines Geräts. Das Payload wird erst vom Dispatcher typisiert (je nach "type").
 */
final readonly class IncomingMutation
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public Uuid $id,
        #[Assert\NotBlank]
        #[Assert\Length(max: 40)]
        public string $type,
        public \DateTimeImmutable $at,
        public array $payload = [],
    ) {
    }
}
