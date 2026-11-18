<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Infrastructure\Doctrine\HouseholdRepository;
use App\Identity\Infrastructure\Doctrine\UserRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConsoleCommandsTest extends KernelTestCase
{
    public function testCreateHouseholdAndUserWithoutExplicitHousehold(): void
    {
        $this->command('app:household:create')->execute(['name' => 'Familie Muster']);

        $tester = $this->command('app:user:create');
        $tester->setInputs(['geheim-geheim', 'geheim-geheim']);
        $exitCode = $tester->execute(['email' => 'Marco@Example.com', 'display-name' => 'Marco']);

        self::assertSame(Command::SUCCESS, $exitCode, $tester->getDisplay());
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('marco@example.com');
        self::assertNotNull($user);
        self::assertSame('Familie Muster', $user->getHousehold()->getName());
    }

    public function testUserCreationNeedsExplicitHouseholdWhenSeveralExist(): void
    {
        $this->command('app:household:create')->execute(['name' => 'Haushalt A']);
        $this->command('app:household:create')->execute(['name' => 'Haushalt B']);

        $tester = $this->command('app:user:create');
        $exitCode = $tester->execute(['email' => 'marco@example.com', 'display-name' => 'Marco']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('--household', $tester->getDisplay());

        $householdB = self::getContainer()->get(HouseholdRepository::class)->findOneBy(['name' => 'Haushalt B']);
        self::assertNotNull($householdB);
        $tester->setInputs(['geheim-geheim', 'geheim-geheim']);
        self::assertSame(Command::SUCCESS, $tester->execute([
            'email' => 'marco@example.com',
            'display-name' => 'Marco',
            '--household' => $householdB->getId()->toRfc4122(),
        ]));
    }

    public function testMismatchingPasswordsAreRejected(): void
    {
        $this->command('app:household:create')->execute(['name' => 'Familie Muster']);

        $tester = $this->command('app:user:create');
        $tester->setInputs(['geheim-geheim', 'vertippt-vertippt']);

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'marco@example.com', 'display-name' => 'Marco']));
        self::assertNull(self::getContainer()->get(UserRepository::class)->findOneByEmail('marco@example.com'));
    }

    public function testSetPassword(): void
    {
        $this->command('app:household:create')->execute(['name' => 'Familie Muster']);
        $create = $this->command('app:user:create');
        $create->setInputs(['geheim-geheim', 'geheim-geheim']);
        $create->execute(['email' => 'marco@example.com', 'display-name' => 'Marco']);

        $tester = $this->command('app:user:set-password');
        $tester->setInputs(['ganz-neues-passwort', 'ganz-neues-passwort']);

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'marco@example.com']), $tester->getDisplay());
    }

    private function command(string $name): CommandTester
    {
        $application = new Application(self::bootKernel());

        return new CommandTester($application->find($name));
    }
}
