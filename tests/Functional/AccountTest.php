<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Doctrine\UserRepository;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

/**
 * „Mehr → Konto“: eigenen Namen und eigenes Passwort ändern.
 */
final class AccountTest extends WebTestCase
{
    use CreatesUsers;

    private const string NEW_PASSWORD = 'neues-passwort-1';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::createUser('marco@example.com', 'Marco');
    }

    public function testRenamesOwnDisplayName(): void
    {
        $this->login(self::PASSWORD);
        $this->client->request('GET', '/mehr');
        $this->client->clickLink('Name');
        self::assertInputValueSame('display_name[displayName]', 'Marco');

        $this->client->submitForm('Speichern', ['display_name[displayName]' => '  Marco S.  ']);

        self::assertResponseRedirects('/mehr', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.notice--success', 'Name geändert');
        self::assertAnySelectorTextContains('.list-group', 'Marco S.');
        self::assertSame('Marco S.', $this->user()->getDisplayName());

        // Die App liest den Namen über /api/me
        $this->client->request('GET', '/api/me');
        self::assertSame('Marco S.', json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['display_name']);
    }

    public function testEmptyNameIsRejected(): void
    {
        $this->login(self::PASSWORD);
        $this->client->request('GET', '/mehr/konto/name');
        $this->client->submitForm('Speichern', ['display_name[displayName]' => '   ']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error', 'Bitte einen Namen eingeben.');
        self::assertSame('Marco', $this->user()->getDisplayName());
    }

    public function testChangesPasswordAndStaysLoggedInOnlyOnThisDevice(): void
    {
        $this->login(self::PASSWORD);
        $oldRememberMe = $this->client->getCookieJar()->get('REMEMBERME');
        self::assertNotNull($oldRememberMe);

        $this->client->request('GET', '/mehr');
        $this->client->clickLink('Passwort');
        $this->client->submitForm('Passwort ändern', [
            'password_change[currentPassword]' => self::PASSWORD,
            'password_change[newPassword][first]' => self::NEW_PASSWORD,
            'password_change[newPassword][second]' => self::NEW_PASSWORD,
        ]);

        self::assertResponseRedirects('/mehr', 303);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.notice--success', 'Passwort geändert');

        // Dieses Gerät: Sitzung gilt weiter, neues Remember-me-Cookie
        $this->client->request('GET', '/mehr');
        self::assertResponseIsSuccessful();
        $newRememberMe = $this->client->getCookieJar()->get('REMEMBERME');
        self::assertNotNull($newRememberMe);
        self::assertNotSame($oldRememberMe->getValue(), $newRememberMe->getValue());
        $this->startAppWith($newRememberMe);
        self::assertResponseIsSuccessful();

        // Anderes Gerät mit altem Cookie: abgemeldet
        $this->startAppWith($oldRememberMe);
        self::assertResponseRedirects('/login');

        // Nur noch das neue Passwort gilt
        $this->client->getCookieJar()->clear();
        $this->login(self::PASSWORD);
        self::assertResponseRedirects('/login');
        $this->login(self::NEW_PASSWORD);
        self::assertResponseRedirects('/');
    }

    public function testWrongCurrentPasswordChangesNothing(): void
    {
        $this->login(self::PASSWORD);
        $this->client->request('GET', '/mehr/konto/passwort');
        $this->client->submitForm('Passwort ändern', [
            'password_change[currentPassword]' => 'falsches-passwort',
            'password_change[newPassword][first]' => self::NEW_PASSWORD,
            'password_change[newPassword][second]' => self::NEW_PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error', 'Das aktuelle Passwort stimmt nicht.');
        $this->assertPasswordIs(self::PASSWORD);
    }

    public function testNewPasswordMustBeLongEnoughAndRepeated(): void
    {
        $this->login(self::PASSWORD);
        $this->client->request('GET', '/mehr/konto/passwort');
        $this->client->submitForm('Passwort ändern', [
            'password_change[currentPassword]' => self::PASSWORD,
            'password_change[newPassword][first]' => 'kurz',
            'password_change[newPassword][second]' => 'kurz',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Mindestens 10 Zeichen.');

        $this->client->submitForm('Passwort ändern', [
            'password_change[currentPassword]' => self::PASSWORD,
            'password_change[newPassword][first]' => self::NEW_PASSWORD,
            'password_change[newPassword][second]' => 'etwas-anderes-1',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Die Passwörter stimmen nicht überein.');
        $this->assertPasswordIs(self::PASSWORD);
    }

    private function login(string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Anmelden')->form(['email' => 'marco@example.com', 'password' => $password]));
    }

    /**
     * Neuer App-Start: Sitzung weg, nur das Remember-me-Cookie bleibt.
     */
    private function startAppWith(Cookie $rememberMe): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->getCookieJar()->set($rememberMe);
        $this->client->request('GET', '/mehr');
    }

    private function assertPasswordIs(string $password): void
    {
        $hasher = self::getContainer()->get('security.user_password_hasher');
        self::assertTrue($hasher->isPasswordValid($this->user(), $password));
    }

    private function user(): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail('marco@example.com');
        self::assertNotNull($user);
        self::getContainer()->get('doctrine')->getManager()->refresh($user);

        return $user;
    }
}
