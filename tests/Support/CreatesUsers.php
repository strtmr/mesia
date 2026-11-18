<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Identity\Application\UserRegistration;
use App\Identity\Domain\Model\User;

/**
 * Für Tests, die von KernelTestCase/WebTestCase erben: legt Haushalt + Benutzer über den echten Anwendungsfall an.
 */
trait CreatesUsers
{
    protected const string PASSWORD = 'geheim-geheim';

    protected static function createUser(
        string $email = 'marco@example.com',
        string $displayName = 'Marco',
        string $householdName = 'Familie Test',
    ): User {
        $registration = self::getContainer()->get(UserRegistration::class);
        $household = $registration->createHousehold($householdName);

        return $registration->createUser($household, $email, $displayName, self::PASSWORD);
    }
}
