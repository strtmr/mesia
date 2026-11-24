<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\Model\User;
use App\Shopping\Application\CreateDefaultStoresOnHouseholdCreated;
use App\Tests\Support\CreatesUsers;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * Läden und „gültig bis“: Startliste, Sync-Regeln an Einträgen, „Mehr → Läden“.
 */
final class StoreTest extends WebTestCase
{
    use CreatesUsers;

    private KernelBrowser $client;
    private User $marco;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->marco = self::createUser();
        $this->client->loginUser($this->marco);
    }

    public function testNewHouseholdsGetTheDefaultStores(): void
    {
        $names = array_column($this->snapshot()['entities']['store'], 'name');
        sort($names);
        $expected = array_values(CreateDefaultStoresOnHouseholdCreated::DEFAULT_STORES);
        sort($expected);

        self::assertSame($expected, $names);
    }

    public function testInitializeDoesNotBringBackDeletedStores(): void
    {
        $this->deleteStore($this->store('Aldi'));

        (new CommandTester((new Application($this->client->getKernel()))->find('app:household:initialize')))->execute([]);

        self::assertNotContains('Aldi', array_column($this->snapshot()['entities']['store'], 'name'));
    }

    public function testInitializeAddsNewDefaultStoresButRespectsOwnOnes(): void
    {
        // Haushalt von „vor der Erweiterung“: Picnic und Fristo gab es damals noch nicht
        self::getContainer()->get(Connection::class)->executeStatement("DELETE FROM store WHERE seed_key IN ('picnic', 'fristo')");
        // … und einen eigenen Laden „fristo“ hat der Haushalt selbst angelegt
        $this->client->request('GET', '/mehr/laeden/neu');
        $this->client->submitForm('Anlegen', ['store[name]' => 'fristo']);

        (new CommandTester((new Application($this->client->getKernel()))->find('app:household:initialize')))->execute([]);

        $names = array_column($this->snapshot()['entities']['store'], 'name');
        self::assertContains('Picnic', $names);
        self::assertContains('fristo', $names);
        self::assertNotContains('Fristo', $names, 'Eigener Laden gleichen Namens: kein zweiter');
        self::assertCount(\count(CreateDefaultStoresOnHouseholdCreated::DEFAULT_STORES), $names);
    }

    public function testAddWithStoresAndDate(): void
    {
        $itemId = Uuid::v7()->toRfc4122();
        $result = $this->push('item.add', $this->addPayload($itemId, 'Bier', ['store_ids' => [$this->store('Edeka'), Uuid::v7()->toRfc4122()], 'valid_until' => '2026-11-28']));

        self::assertSame('applied', $result['status']);
        $item = $this->item($itemId);
        self::assertSame([$this->store('Edeka')], $item['store_ids'], 'Unbekannte Läden fallen still weg');
        self::assertSame('2026-11-28', $item['valid_until']);
    }

    public function testInvalidDateIsRejected(): void
    {
        $result = $this->push('item.add', $this->addPayload(Uuid::v7()->toRfc4122(), 'Bier', ['valid_until' => '2026-02-30']));

        self::assertSame('rejected', $result['status']);
        self::assertSame('Ungültiges Datum.', $result['error']);
    }

    public function testAddingAgainAddsStoresAndReactivatingForgetsTheDate(): void
    {
        $itemId = Uuid::v7()->toRfc4122();
        $this->push('item.add', $this->addPayload($itemId, 'Bier', ['store_ids' => [$this->store('Lidl')], 'valid_until' => '2026-11-28']));
        $merged = $this->push('item.add', $this->addPayload(Uuid::v7()->toRfc4122(), 'Bier', ['store_ids' => [$this->store('Edeka')]]));
        self::assertSame('merged', $merged['status']);
        self::assertSame([$this->store('Lidl'), $this->store('Edeka')], $this->item($itemId)['store_ids']);
        self::assertSame('2026-11-28', $this->item($itemId)['valid_until']);

        $this->push('item.check', ['item_id' => $itemId]);
        $this->push('item.add', $this->addPayload(Uuid::v7()->toRfc4122(), 'Bier'));

        self::assertNull($this->item($itemId)['checked_at']);
        self::assertNull($this->item($itemId)['valid_until'], 'Angebot gilt nur für die alte Runde');
        self::assertSame([$this->store('Lidl'), $this->store('Edeka')], $this->item($itemId)['store_ids'], 'Der Laden bleibt');
    }

    public function testUpdateStoresAndDate(): void
    {
        $itemId = Uuid::v7()->toRfc4122();
        $this->push('item.add', $this->addPayload($itemId, 'Bier'));

        self::assertSame('applied', $this->push('item.update', ['item_id' => $itemId, 'fields' => ['store_ids' => [$this->store('Rewe')], 'valid_until' => '2026-12-01']])['status']);
        self::assertSame([$this->store('Rewe')], $this->item($itemId)['store_ids']);
        self::assertSame('2026-12-01', $this->item($itemId)['valid_until']);

        $this->push('item.update', ['item_id' => $itemId, 'fields' => ['store_ids' => [], 'valid_until' => null]]);
        self::assertSame([], $this->item($itemId)['store_ids']);
        self::assertNull($this->item($itemId)['valid_until']);

        self::assertSame('rejected', $this->push('item.update', ['item_id' => $itemId, 'fields' => ['store_ids' => 'Edeka']])['status']);
    }

    public function testManageStores(): void
    {
        $this->client->request('GET', '/mehr');
        self::assertSelectorExists('a[href="/mehr/laeden"]');

        $this->client->request('GET', '/mehr/laeden');
        self::assertSelectorTextContains('.list-group', 'Rossmann');
        self::assertSelectorExists('nav.tabbar a[href="/mehr"][aria-current="page"]');

        $this->client->request('GET', '/mehr/laeden/neu');
        $this->client->submitForm('Anlegen', ['store[name]' => 'edeka']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error', 'Es gibt schon einen Laden „Edeka“.');

        $this->client->submitForm('Anlegen', ['store[name]' => '@Markt']);
        self::assertSelectorTextContains('.form-error', 'Bitte ohne „@“');

        $this->client->submitForm('Anlegen', ['store[name]' => 'Hofladen Meier']);
        self::assertResponseRedirects('/mehr/laeden', 303);
        self::assertContains('Hofladen Meier', array_column($this->snapshot()['entities']['store'], 'name'));

        $this->client->request('GET', '/mehr/laeden/'.$this->store('Hofladen Meier'));
        $this->client->submitForm('Speichern', ['store[name]' => 'Biohof Meier']);
        self::assertResponseRedirects('/mehr/laeden', 303);

        $this->deleteStore($this->store('Biohof Meier'));
        self::assertNotContains('Biohof Meier', array_column($this->snapshot()['entities']['store'], 'name'));
    }

    public function testStoresOfOtherHouseholdsAreInvisible(): void
    {
        $edeka = $this->store('Edeka');
        $this->client->loginUser(self::createUser('anna@example.com', 'Anna', 'Familie Anna'));

        $this->client->request('GET', '/mehr/laeden/'.$edeka);
        self::assertResponseStatusCodeSame(404);
    }

    // ---------------------------------------------------------------- Hilfsmethoden

    private function deleteStore(string $id): void
    {
        $this->client->request('GET', '/mehr/laeden/'.$id);
        $this->client->submitForm('Laden löschen');
        self::assertResponseRedirects('/mehr/laeden', 303);
    }

    private function store(string $name): string
    {
        foreach ($this->snapshot()['entities']['store'] as $store) {
            if ($store['name'] === $name) {
                return $store['id'];
            }
        }

        self::fail(\sprintf('Laden %s fehlt', $name));
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $id): array
    {
        foreach ($this->snapshot()['entities']['shopping_item'] ?? [] as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        self::fail('Eintrag fehlt');
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function addPayload(string $itemId, string $articleName, array $extra = []): array
    {
        $snapshot = $this->snapshot();
        $article = array_column($snapshot['entities']['article'], null, 'name')[$articleName];

        return [
            'item_id' => $itemId,
            'list_id' => $snapshot['entities']['shopping_list'][0]['id'],
            'article_id' => $article['id'],
            'name' => $articleName,
            'category_id' => $article['category_id'],
            'sort_key' => 'a0',
            'contribution' => ['id' => Uuid::v7()->toRfc4122(), 'quantity' => null, 'unit' => null],
        ] + $extra;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function push(string $type, array $payload): array
    {
        $mutation = ['id' => Uuid::v7()->toRfc4122(), 'type' => $type, 'at' => (new \DateTimeImmutable())->format(\DATE_RFC3339_EXTENDED), 'payload' => $payload];
        $this->client->request('POST', '/api/sync/push', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'mesia'], content: json_encode(['device_id' => 'test', 'mutations' => [$mutation]], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['results'][0];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $this->client->request('GET', '/api/sync/snapshot');

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
