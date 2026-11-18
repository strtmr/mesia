<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity;

use App\Identity\Domain\Model\Household;
use App\Identity\Domain\Model\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEmailIsNormalizedToLowercase(): void
    {
        $user = new User($this->household(), '  Marco@Example.COM ', 'Marco', new \DateTimeImmutable());

        self::assertSame('marco@example.com', $user->getEmail());
        self::assertSame('marco@example.com', $user->getUserIdentifier());
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User($this->household(), 'keine-mail', 'Marco', new \DateTimeImmutable());
    }

    public function testEmptyDisplayNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User($this->household(), 'marco@example.com', '   ', new \DateTimeImmutable());
    }

    public function testSerializedUserDoesNotContainThePasswordHash(): void
    {
        $user = new User($this->household(), 'marco@example.com', 'Marco', new \DateTimeImmutable());
        $user->setPasswordHash('$2y$13$supersecrethashvalue');

        self::assertStringNotContainsString('supersecrethashvalue', serialize($user));
    }

    public function testHouseholdNameMustNotBeEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Household(' ', new \DateTimeImmutable());
    }

    private function household(): Household
    {
        return new Household('Familie Test', new \DateTimeImmutable());
    }
}
