<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\ChangeLog;

use App\Shared\Application\ChangeLog\ChangeContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ChangeContextTest extends TestCase
{
    public function testMutationIdIsOnlySetWhileOperationRuns(): void
    {
        $context = new ChangeContext();
        $mutationId = Uuid::v7();

        $seenInside = $context->runForMutation($mutationId, static fn (): ?Uuid => $context->mutationId());

        self::assertSame($mutationId, $seenInside);
        self::assertNull($context->mutationId());
    }

    public function testMutationIdIsRestoredWhenOperationThrows(): void
    {
        $context = new ChangeContext();

        try {
            $context->runForMutation(Uuid::v7(), static fn () => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
            // erwartet: die Closure wirft immer
        }

        self::assertNull($context->mutationId());
    }

    public function testResetClearsActor(): void
    {
        $context = new ChangeContext();
        $context->setActorId(Uuid::v7());

        $context->reset();

        self::assertNull($context->actorId());
    }
}
