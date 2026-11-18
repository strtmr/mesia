<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\UserRegistration;
use App\Identity\Infrastructure\Doctrine\UserRepository;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserRegistrationTest extends KernelTestCase
{
    use CreatesUsers;

    public function testCreatedUserCanBeFoundCaseInsensitivelyAndHasAHashedPassword(): void
    {
        self::bootKernel();
        self::createUser(email: 'Marco@Example.com');

        $user = self::getContainer()->get(UserRepository::class)->loadUserByIdentifier('MARCO@example.COM');

        self::assertNotNull($user);
        self::assertNotSame(self::PASSWORD, $user->getPassword());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, self::PASSWORD));
    }

    public function testDuplicateEmailIsRejectedRegardlessOfCase(): void
    {
        self::bootKernel();
        $user = self::createUser(email: 'marco@example.com');

        $this->expectException(\DomainException::class);
        self::getContainer()->get(UserRegistration::class)->createUser($user->getHousehold(), 'MARCO@example.com', 'Zweiter', self::PASSWORD);
    }

    public function testShortPasswordIsRejected(): void
    {
        self::bootKernel();
        $registration = self::getContainer()->get(UserRegistration::class);
        $household = $registration->createHousehold('Familie Test');

        $this->expectException(\InvalidArgumentException::class);
        $registration->createUser($household, 'marco@example.com', 'Marco', 'kurz');
    }

    public function testChangePassword(): void
    {
        self::bootKernel();
        $user = self::createUser();

        self::getContainer()->get(UserRegistration::class)->changePassword('marco@example.com', 'ganz-neues-passwort');

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'ganz-neues-passwort'));
        self::assertFalse($hasher->isPasswordValid($user, self::PASSWORD));
    }
}
