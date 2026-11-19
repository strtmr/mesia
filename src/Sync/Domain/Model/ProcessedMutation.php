<?php

declare(strict_types=1);

namespace App\Sync\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Merkt sich jede verarbeitete Mutation samt Ergebnis. Schickt ein Gerät dieselbe Mutation erneut (z. B. weil die
 * Antwort unterwegs verloren ging), bekommt es dasselbe Ergebnis, ohne dass die Mutation ein zweites Mal wirkt.
 */
#[ORM\Entity]
#[ORM\Table(name: 'processed_mutation')]
#[ORM\Index(name: 'processed_mutation_household_idx', columns: ['household_id', 'processed_at'])]
class ProcessedMutation
{
    /**
     * @param array<string, mixed> $result
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $mutationId,
        #[ORM\Column(type: UuidType::NAME)]
        private Uuid $householdId,
        #[ORM\Column(length: 64)]
        private string $deviceId,
        #[ORM\Column(length: 40)]
        private string $type,
        #[ORM\Column(length: 16)]
        private string $status,
        #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
        private array $result,
        #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
        private \DateTimeImmutable $processedAt,
    ) {
    }

    public function getMutationId(): Uuid
    {
        return $this->mutationId;
    }

    public function getHouseholdId(): Uuid
    {
        return $this->householdId;
    }

    public function getDeviceId(): string
    {
        return $this->deviceId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResult(): array
    {
        return $this->result;
    }

    public function getProcessedAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }
}
