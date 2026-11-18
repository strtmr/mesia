<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityTest extends WebTestCase
{
    use CreatesUsers;

    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $client = self::createClient();

        $client->request('GET', '/einkauf');

        self::assertResponseRedirects('/login');
    }

    public function testLoginPageIsPublicAndReadyForTheIphone(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="email"][type="email"][autocomplete="username"]');
        self::assertSelectorExists('link[rel="manifest"][href="/manifest.webmanifest"]');
        self::assertSelectorExists('link[rel="apple-touch-icon"]');
        self::assertSelectorExists('meta[name="viewport"][content*="viewport-fit=cover"]');
    }

    public function testWrongPasswordShowsAnError(): void
    {
        $client = self::createClient();
        self::createUser();

        $this->login($client, 'marco@example.com', 'falsches-passwort');

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorExists('.form-error');
        self::assertInputValueSame('email', 'marco@example.com');
    }

    public function testLoginIsCaseInsensitiveAndSetsALongLivedRememberMeCookie(): void
    {
        $client = self::createClient();
        self::createUser();

        $this->login($client, 'MARCO@example.com', self::PASSWORD);

        self::assertResponseRedirects('/');
        $cookie = $client->getCookieJar()->get('REMEMBERME');
        self::assertNotNull($cookie, 'Remember-me-Cookie fehlt.');
        self::assertGreaterThan(time() + 300 * 24 * 3600, (int) $cookie->getExpiresTime());
        self::assertTrue($cookie->isHttpOnly());

        $client->followRedirect();
        self::assertResponseRedirects('/einkauf');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Einkauf');
        self::assertSelectorExists('nav.tabbar a[aria-current="page"][href="/einkauf"]');
    }

    public function testRememberMeCookieLogsInAfterTheSessionIsGone(): void
    {
        $client = self::createClient();
        self::createUser();
        $this->login($client, 'marco@example.com', self::PASSWORD);
        $rememberMe = $client->getCookieJar()->get('REMEMBERME');
        self::assertNotNull($rememberMe);

        // Neuer "App-Start": Session weg, nur das Remember-me-Cookie bleibt.
        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($rememberMe);
        $client->request('GET', '/mehr');

        self::assertResponseIsSuccessful();
    }

    public function testMorePageShowsAccountAndAllowsLogout(): void
    {
        $client = self::createClient();
        $user = self::createUser(displayName: 'Marco', householdName: 'Familie Muster');
        $client->loginUser($user);

        $client->request('GET', '/mehr');

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('.list-group', 'Marco');
        self::assertAnySelectorTextContains('.list-group', 'Familie Muster');
        self::assertAnySelectorTextContains('.list-group', '0.1.0-dev');

        $client->clickLink('Abmelden');
        self::assertResponseRedirects('/login');
        $client->request('GET', '/mehr');
        self::assertResponseRedirects('/login');
    }

    private function login(KernelBrowser $client, string $email, string $password): void
    {
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Anmelden')->form([
            'email' => $email,
            'password' => $password,
        ]);
        $client->submit($form);
    }
}
