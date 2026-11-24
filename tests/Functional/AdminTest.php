<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Application\UserRegistration;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\Doctrine\HouseholdRepository;
use App\Identity\Infrastructure\Doctrine\UserRepository;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * Admin-Bereich: Zugang nur mit ROLE_ADMIN, Überblick, Auswertungen, Haushalte und Personen anlegen.
 */
final class AdminTest extends WebTestCase
{
    use CreatesUsers;

    private KernelBrowser $client;
    private User $admin;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->admin = self::createUser('admin@example.com', 'Marco', 'Familie Admin');
        self::getContainer()->get(UserRegistration::class)->changeAdmin('admin@example.com', true);
    }

    public function testOnlyAdminsGetIn(): void
    {
        $user = self::createUser('anna@example.com', 'Anna', 'Familie Anna');
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/mehr');
        self::assertSelectorNotExists('a[href="/admin"]');

        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/mehr');
        self::assertSelectorExists('a[href="/admin"]');
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
    }

    public function testGrantAdminCommand(): void
    {
        self::createUser('anna@example.com', 'Anna', 'Familie Anna');
        $command = new CommandTester((new Application($this->client->getKernel()))->find('app:user:admin'));

        self::assertSame(0, $command->execute(['email' => 'Anna@Example.com']));
        self::assertTrue($this->user('anna@example.com')->isAdmin());
        self::assertContains('ROLE_ADMIN', $this->user('anna@example.com')->getRoles());

        self::assertSame(0, $command->execute(['email' => 'anna@example.com', '--revoke' => true]));
        self::assertFalse($this->user('anna@example.com')->isAdmin());

        self::assertSame(1, $command->execute(['email' => 'niemand@example.com']));
    }

    public function testDashboardListsAllHouseholds(): void
    {
        self::createUser('anna@example.com', 'Anna', 'Familie Anna');
        $this->client->loginUser($this->admin);

        $crawler = $this->client->request('GET', '/admin');

        self::assertSelectorTextContains('.list-group', 'Haushalte');
        self::assertCount(2, $crawler->filter('.admin-household'));
        self::assertSelectorTextContains('main', 'Familie Anna');
        self::assertSelectorTextContains('main', 'Familie Admin');
    }

    public function testCreatesHouseholdWithFirstPerson(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/haushalte/neu');
        $this->client->submitForm('Anlegen', [
            'person[householdName]' => 'WG Südstadt',
            'person[displayName]' => 'Lena',
            'person[email]' => 'lena@example.com',
            'person[password][first]' => 'lena-passwort-1',
            'person[password][second]' => 'lena-passwort-1',
        ]);

        self::assertResponseRedirects('/admin', 303);
        $lena = $this->user('lena@example.com');
        self::assertSame('WG Südstadt', $lena->getHousehold()->getName());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.notice--success', 'WG Südstadt');

        // Grunddaten wie bei app:household:create: Lena hat eine Liste und den Startkatalog
        $this->client->loginUser($lena);
        $this->client->request('GET', '/api/sync/snapshot');
        $snapshot = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $snapshot['entities']['shopping_list']);
        self::assertNotEmpty($snapshot['entities']['article']);
    }

    public function testTakenEmailCreatesNothing(): void
    {
        $this->client->loginUser($this->admin);
        $households = \count(self::getContainer()->get(HouseholdRepository::class)->findAll());

        $this->client->request('GET', '/admin/haushalte/neu');
        $this->client->submitForm('Anlegen', [
            'person[householdName]' => 'Doppelt',
            'person[displayName]' => 'Marco',
            'person[email]' => 'ADMIN@example.com',
            'person[password][first]' => 'irgendein-passwort',
            'person[password][second]' => 'irgendein-passwort',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error', 'schon ein Konto');
        self::assertCount($households, self::getContainer()->get(HouseholdRepository::class)->findAll());
    }

    public function testValidatesPasswords(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/haushalte/'.$this->admin->getHousehold()->getId()->toRfc4122().'/person');
        $this->client->submitForm('Anlegen', [
            'person[displayName]' => 'Anna',
            'person[email]' => 'anna@example.com',
            'person[password][first]' => 'kurz',
            'person[password][second]' => 'anders',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Die Passwörter stimmen nicht überein.');
        self::assertNull(self::getContainer()->get(UserRepository::class)->findOneByEmail('anna@example.com'));
    }

    public function testAddsPersonToHousehold(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/haushalte/'.$this->admin->getHousehold()->getId()->toRfc4122().'/person');
        $this->client->submitForm('Anlegen', [
            'person[displayName]' => 'Anna',
            'person[email]' => 'anna@example.com',
            'person[password][first]' => 'anna-passwort-1',
            'person[password][second]' => 'anna-passwort-1',
        ]);

        self::assertResponseRedirects('/admin', 303);
        self::assertTrue($this->user('anna@example.com')->getHousehold()->getId()->equals($this->admin->getHousehold()->getId()));
    }

    public function testReportsShowManualArticlesAndPurchases(): void
    {
        $this->client->loginUser($this->admin);
        $snapshot = $this->snapshot();
        $listId = $snapshot['entities']['shopping_list'][0]['id'];
        $milk = array_column($snapshot['entities']['article'], null, 'name')['Milch'];
        $fallback = array_values(array_filter($snapshot['entities']['category'], static fn (array $c): bool => $c['is_fallback']))[0];

        $itemId = Uuid::v7()->toRfc4122();
        $this->push([
            ['type' => 'article.create', 'payload' => ['article_id' => Uuid::v7()->toRfc4122(), 'name' => 'Quarkbällchen', 'category_id' => $fallback['id']]],
            ['type' => 'item.add', 'payload' => [
                'item_id' => $itemId, 'list_id' => $listId, 'article_id' => $milk['id'], 'name' => 'Milch', 'category_id' => $milk['category_id'],
                'sort_key' => 'a0', 'contribution' => ['id' => Uuid::v7()->toRfc4122(), 'quantity' => null, 'unit' => null],
            ]],
            ['type' => 'item.check', 'payload' => ['item_id' => $itemId]],
        ]);

        $this->client->request('GET', '/admin/artikel');
        self::assertSelectorTextContains('main', 'Quarkbällchen');
        self::assertSelectorTextNotContains('main', 'Milch');

        $this->client->request('GET', '/admin/einkaeufe?zeitraum=30&haushalt='.$this->admin->getHousehold()->getId()->toRfc4122());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main ol', '1. Milch');
        self::assertSelectorTextContains('main ol', '1× gekauft');

        $this->client->request('GET', '/admin/einkaeufe?zeitraum=ewig');
        self::assertResponseStatusCodeSame(404);
    }

    public function testUserArticlesShowTheCurrentLabelAndCanBeDeleted(): void
    {
        $this->client->loginUser($this->admin);
        $snapshot = $this->snapshot();
        $listId = $snapshot['entities']['shopping_list'][0]['id'];
        $fallback = array_values(array_filter($snapshot['entities']['category'], static fn (array $c): bool => $c['is_fallback']))[0];

        $balls = ['article' => Uuid::v7()->toRfc4122(), 'item' => Uuid::v7()->toRfc4122()];
        $buns = ['article' => Uuid::v7()->toRfc4122(), 'item' => Uuid::v7()->toRfc4122()];
        $add = static fn (array $ids, string $name): array => ['type' => 'item.add', 'payload' => [
            'item_id' => $ids['item'], 'list_id' => $listId, 'article_id' => $ids['article'], 'name' => $name, 'category_id' => $fallback['id'],
            'sort_key' => 'a0', 'contribution' => ['id' => Uuid::v7()->toRfc4122(), 'quantity' => null, 'unit' => null],
        ]];
        $this->push([
            ['type' => 'article.create', 'payload' => ['article_id' => $balls['article'], 'name' => 'quarkbällchen', 'category_id' => $fallback['id']]],
            $add($balls, 'quarkbällchen'),
            // In der App umbenannt: ändert nur den Eintrag, nicht den Katalogartikel
            ['type' => 'item.update', 'payload' => ['item_id' => $balls['item'], 'fields' => ['name' => 'Quarkbällchen vom Bäcker']]],
            ['type' => 'item.check', 'payload' => ['item_id' => $balls['item']]],
            ['type' => 'article.create', 'payload' => ['article_id' => $buns['article'], 'name' => 'Zimtschnecken', 'category_id' => $fallback['id']]],
            $add($buns, 'Zimtschnecken'),
        ]);

        $crawler = $this->client->request('GET', '/admin/artikel');
        self::assertSelectorTextContains('main', 'Quarkbällchen vom Bäcker');
        self::assertSelectorTextNotContains('main', 'quarkbällchen');

        // Löschen: Quarkbällchen (erledigt) und Zimtschnecken (offen)
        foreach (['Quarkbällchen vom Bäcker', 'Zimtschnecken'] as $name) {
            $form = $crawler->filter('form')->reduce(static fn ($form): bool => $form->filter('input[name="name"]')->attr('value') === $name)->form();
            $this->client->submit($form);
            self::assertResponseRedirects('/admin/artikel', 303);
            $crawler = $this->client->followRedirect();
            self::assertSelectorTextContains('.notice--success', '(1 Haushalt)');
        }
        self::assertSelectorTextContains('main', 'Noch keine selbst angelegten Artikel.');

        $snapshot = $this->snapshot();
        $articleIds = array_column($snapshot['entities']['article'], 'id');
        self::assertNotContains($balls['article'], $articleIds);
        self::assertNotContains($buns['article'], $articleIds);
        $itemIds = array_column($snapshot['entities']['shopping_item'] ?? [], 'id');
        self::assertNotContains($balls['item'], $itemIds, 'Erledigter Eintrag verschwindet mit dem Artikel');
        self::assertContains($buns['item'], $itemIds, 'Offener Eintrag bleibt auf der Liste');
    }

    private function user(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneByEmail($email);
        self::assertNotNull($user);
        self::getContainer()->get('doctrine')->getManager()->refresh($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $this->client->request('GET', '/api/sync/snapshot');

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array{type: string, payload: array<string, mixed>}> $mutations
     */
    private function push(array $mutations): void
    {
        $mutations = array_map(static fn (array $m): array => ['id' => Uuid::v7()->toRfc4122(), 'at' => (new \DateTimeImmutable())->format(\DATE_RFC3339_EXTENDED)] + $m, $mutations);
        $this->client->request('POST', '/api/sync/push', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'mesia'], content: json_encode(['device_id' => 'test', 'mutations' => $mutations], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        foreach (json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['results'] as $result) {
            self::assertSame('applied', $result['status'], json_encode($result, \JSON_THROW_ON_ERROR));
        }
    }
}
