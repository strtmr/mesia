<?php

declare(strict_types=1);

namespace App\Shared\Application\ChangeLog;

use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Wer ändert gerade etwas, und im Rahmen welcher Sync-Mutation?
 *
 * Wird pro Request befüllt (Benutzer nach dem Login, Mutation-ID beim Sync-Push) und vom Änderungsprotokoll gelesen.
 */
final class ChangeContext implements ResetInterface
{
    private ?Uuid $actorId = null;
    private ?Uuid $mutationId = null;

    public function setActorId(?Uuid $actorId): void
    {
        $this->actorId = $actorId;
    }

    public function actorId(): ?Uuid
    {
        return $this->actorId;
    }

    public function mutationId(): ?Uuid
    {
        return $this->mutationId;
    }

    /**
     * Führt $operation im Kontext einer Sync-Mutation aus.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function runForMutation(Uuid $mutationId, callable $operation): mixed
    {
        $previous = $this->mutationId;
        $this->mutationId = $mutationId;

        try {
            return $operation();
        } finally {
            $this->mutationId = $previous;
        }
    }

    public function reset(): void
    {
        $this->actorId = null;
        $this->mutationId = null;
    }
}
