<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\Model\User;
use App\Tests\Support\CreatesUsers;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Sync-API so, wie der Offline-Client sie nutzt: snapshot → push → pull.
 * Regeln: docs/spezifikation-phase-0-1.md, Kapitel 4.
 */
final class SyncApiTest extends WebTestCase
{
    use CreatesUsers;

    private KernelBrowser $client;
    private User $user;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->user = self::createUser();
        $this->client->loginUser($this->user);
    }

    public function testApiAnswers401WhenNotLoggedIn(): void
    {
        $anonymous = $this->client;
        $anonymous->getCookieJar()->clear();
        $anonymous->restart();

        $anonymous->request('GET', '/api/sync/snapshot');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'unauthenticated'], $this->json());
    }

    public function testMe(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertResponseIsSuccessful();
        $me = $this->json();
        self::assertSame('marco@example.com', $me['email']);
        self::assertSame($this->user->getHousehold()->getId()->toRfc4122(), $me['household']['id']);
    }

    public function testSnapshotContainsTheStarterCatalogAndTheEmptyList(): void
    {
        $snapshot = $this->snapshot();

        self::assertCount(17, $snapshot['entities']['category']);
        self::assertCount(335, $snapshot['entities']['article']);
        self::assertCount(274, $snapshot['entities']['article_alias']);
        self::assertCount(1, $snapshot['entities']['shopping_list']);
        self::assertArrayNotHasKey('shopping_item', $snapshot['entities']);
        self::assertGreaterThan(0, $snapshot['cursor']);
    }

    public function testPushNeedsTheRequestHeaderAndAMatchingOrigin(): void
    {
        $this->client->request('POST', '/api/sync/push', server: ['CONTENT_TYPE' => 'application/json'], content: '{"device_id":"x","mutations":[]}');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/api/sync/push', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'mealplanner',
            'HTTP_ORIGIN' => 'https://evil.example',
        ], content: '{"device_id":"x","mutations":[]}');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAddItemIsAppliedAndAppearsInPull(): void
    {
        $cursor = $this->snapshot()['cursor'];
        $itemId = $this->uuid();

        $result = $this->pushOne('item.add', $this->addItemPayload($itemId, 'Milch', quantity: 2, unit: 'l'));

        self::assertSame('applied', $result['status']);
        $item = $this->pulledEntity($cursor, 'shopping_item', $itemId);
        self::assertSame('Milch', $item['name']);
        self::assertSame($this->article('Milch')['id'], $item['article_id']);
        self::assertCount(1, $item['contributions']);
        self::assertEquals(2, $item['contributions'][0]['quantity']);
        self::assertSame('l', $item['contributions'][0]['unit']);
        self::assertSame(
            $this->user->getId()->toRfc4122(),
            self::getContainer()->get(Connection::class)->fetchOne('SELECT actor_id FROM change_log WHERE entity_id = ? ORDER BY seq DESC LIMIT 1', [$itemId]),
            'Das Änderungsprotokoll kennt den Urheber.',
        );
    }

    public function testSameMutationTwiceIsOnlyAppliedOnce(): void
    {
        $mutation = $this->mutation('item.add', $this->addItemPayload($this->uuid(), 'Brot'));

        $first = $this->push([$mutation])[0];
        $second = $this->push([$mutation])[0];

        self::assertSame('applied', $first['status']);
        self::assertSame('duplicate', $second['status']);
        self::assertSame('applied', $second['original_status']);
        self::assertCount(1, $this->snapshot()['entities']['shopping_item']);
    }

    public function testTwoDevicesAddingTheSameArticleOfflineAreMerged(): void
    {
        $firstId = $this->uuid();

        $results = $this->push([
            $this->mutation('item.add', $this->addItemPayload($firstId, 'Milch', quantity: 1, unit: 'l')),
            $this->mutation('item.add', $this->addItemPayload($this->uuid(), 'Milch', quantity: 2, unit: 'l')),
        ]);

        self::assertSame('applied', $results[0]['status']);
        self::assertSame('merged', $results[1]['status']);
        self::assertSame($firstId, $results[1]['merged_into']);
        $items = $this->snapshot()['entities']['shopping_item'];
        self::assertCount(1, $items);
        self::assertCount(2, $items[0]['contributions']);
    }

    public function testOfflineCheckOlderThanALaterReactivationIsIgnored(): void
    {
        $itemId = $this->uuid();
        $this->pushOne('item.add', $this->addItemPayload($itemId, 'Butter'), at: '-60 minutes');
        self::assertSame('applied', $this->pushOne('item.check', ['item_id' => $itemId], at: '-50 minutes')['status']);
        self::assertSame('applied', $this->pushOne('item.reactivate', ['item_id' => $itemId, 'contribution' => ['id' => $this->uuid()]], at: '-30 minutes')['status']);

        // Gerät B hat um -40 min offline abgehakt und synchronisiert erst jetzt
        self::assertSame('ignored', $this->pushOne('item.check', ['item_id' => $itemId], at: '-40 minutes')['status']);
        self::assertNull($this->item($itemId)['checked_at']);

        self::assertSame('applied', $this->pushOne('item.check', ['item_id' => $itemId], at: '-10 minutes')['status']);
        self::assertNotNull($this->item($itemId)['checked_at']);
        self::assertSame(2, $this->purchaseEvents($itemId));
    }

    public function testUncheckRemovesThePurchaseEvent(): void
    {
        $itemId = $this->uuid();
        $this->pushOne('item.add', $this->addItemPayload($itemId, 'Eier'), at: '-5 minutes');
        $this->pushOne('item.check', ['item_id' => $itemId], at: '-4 minutes');

        self::assertSame('applied', $this->pushOne('item.uncheck', ['item_id' => $itemId])['status']);
        self::assertSame(0, $this->purchaseEvents($itemId));
        self::assertSame('ignored', $this->pushOne('item.uncheck', ['item_id' => $itemId])['status']);
    }

    public function testMovingToAnotherCategoryTeachesTheArticle(): void
    {
        $itemId = $this->uuid();
        $this->pushOne('item.add', $this->addItemPayload($itemId, 'Milch'));
        $drinks = $this->category('drinks');

        $result = $this->pushOne('item.move', ['item_id' => $itemId, 'category_id' => $drinks['id'], 'sort_key' => 'a5']);

        self::assertSame('applied', $result['status']);
        self::assertSame($drinks['id'], $this->item($itemId)['category_id']);
        self::assertSame($drinks['id'], $this->article('Milch')['category_id'], 'Der Artikel merkt sich die neue Kategorie.');
    }

    public function testUpdateItemFields(): void
    {
        $itemId = $this->uuid();
        $this->pushOne('item.add', $this->addItemPayload($itemId, 'Käse'));

        $result = $this->pushOne('item.update', ['item_id' => $itemId, 'fields' => ['note' => 'Bergkäse', 'qty_override' => 400, 'unit_override' => 'g']]);

        self::assertSame('applied', $result['status']);
        $item = $this->item($itemId);
        self::assertSame('Bergkäse', $item['note']);
        self::assertEquals(400, $item['qty_override']);
        self::assertSame('g', $item['unit_override']);

        $rejected = $this->pushOne('item.update', ['item_id' => $itemId, 'fields' => ['farbe' => 'gelb']]);
        self::assertSame('rejected', $rejected['status']);
        self::assertStringContainsString('farbe', $rejected['error']);
    }

    public function testDeleteRemovesTheItemFromSnapshotAndPullReportsADelete(): void
    {
        $itemId = $this->uuid();
        $this->pushOne('item.add', $this->addItemPayload($itemId, 'Zucker'));
        $cursor = $this->snapshot()['cursor'];

        self::assertSame('applied', $this->pushOne('item.delete', ['item_id' => $itemId])['status']);

        self::assertArrayNotHasKey('shopping_item', $this->snapshot()['entities']);
        $change = $this->pull($cursor)['changes'][0];
        self::assertSame('delete', $change['op']);
        self::assertTrue($change['data']['deleted']);
        self::assertSame('ignored', $this->pushOne('item.delete', ['item_id' => $itemId])['status']);
    }

    public function testCreatingAnExistingArticleIsMergedAndAliasesAreLearned(): void
    {
        $milk = $this->article('Milch');

        $merged = $this->pushOne('article.create', ['article_id' => $this->uuid(), 'name' => ' MILCH ', 'category_id' => $milk['category_id']]);
        self::assertSame('merged', $merged['status']);
        self::assertSame($milk['id'], $merged['merged_into']);

        $dragonFruitId = $this->uuid();
        $created = $this->pushOne('article.create', ['article_id' => $dragonFruitId, 'name' => 'Drachenfrucht', 'category_id' => $this->category('produce')['id']]);
        self::assertSame('applied', $created['status']);

        self::assertSame('applied', $this->pushOne('alias.add', ['alias_id' => $this->uuid(), 'article_id' => $dragonFruitId, 'alias' => 'Pitahaya'])['status']);
        self::assertSame('ignored', $this->pushOne('alias.add', ['alias_id' => $this->uuid(), 'article_id' => $dragonFruitId, 'alias' => 'pitahaya'])['status']);
        $conflict = $this->pushOne('alias.add', ['alias_id' => $this->uuid(), 'article_id' => $dragonFruitId, 'alias' => 'Vollmilch']);
        self::assertSame('rejected', $conflict['status']);
    }

    public function testInvalidMutationsAreRejectedWithoutStoppingTheOthers(): void
    {
        $validId = $this->uuid();

        $results = $this->push([
            $this->mutation('item.fly', ['item_id' => $this->uuid()]),
            $this->mutation('item.add', ['item_id' => $this->uuid()]),
            $this->mutation('item.add', $this->addItemPayload($validId, 'Mehl')),
            $this->mutation('item.check', ['item_id' => $this->uuid()]),
        ]);

        self::assertSame(['rejected', 'rejected', 'applied', 'rejected'], array_column($results, 'status'));
        self::assertStringContainsString('Unbekannter Mutationstyp', $results[0]['error']);
        self::assertStringContainsString('nicht gefunden', $results[3]['error']);
    }

    public function testHouseholdsCannotTouchEachOthersData(): void
    {
        $itemId = $this->uuid();
        $this->pushOne('item.add', $this->addItemPayload($itemId, 'Milch'));
        $myList = $this->snapshot()['entities']['shopping_list'][0]['id'];

        $this->client->loginUser(self::createUser('fremd@example.com', 'Fremd', 'Andere Familie'));

        self::assertSame('rejected', $this->pushOne('item.check', ['item_id' => $itemId])['status']);
        $foreignAdd = $this->addItemPayload($this->uuid(), 'Milch');
        $foreignAdd['list_id'] = $myList;
        self::assertSame('rejected', $this->pushOne('item.add', $foreignAdd)['status']);
        self::assertArrayNotHasKey('shopping_item', $this->snapshot()['entities']);
    }

    public function testPullPagesThroughTheChangeLog(): void
    {
        $cursor = $this->snapshot()['cursor'];
        $this->pushOne('item.add', $this->addItemPayload($this->uuid(), 'Milch'));
        $this->pushOne('item.add', $this->addItemPayload($this->uuid(), 'Brot'));

        $page = $this->pull($cursor, limit: 1);
        self::assertCount(1, $page['changes']);
        self::assertTrue($page['has_more']);

        $rest = $this->pull($page['cursor'], limit: 10);
        self::assertCount(1, $rest['changes']);
        self::assertFalse($rest['has_more']);
        self::assertSame($rest['cursor'], $this->pull($rest['cursor'])['cursor']);
    }

    // ---------------------------------------------------------------- Hilfsmethoden

    /**
     * @return array<string, mixed>
     */
    private function addItemPayload(string $itemId, string $articleName, ?float $quantity = null, ?string $unit = null): array
    {
        $article = $this->article($articleName);

        return [
            'item_id' => $itemId,
            'list_id' => $this->snapshot()['entities']['shopping_list'][0]['id'],
            'article_id' => $article['id'],
            'name' => $article['name'],
            'category_id' => $article['category_id'],
            'sort_key' => 'a0',
            'contribution' => ['id' => $this->uuid(), 'quantity' => $quantity, 'unit' => $unit],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function mutation(string $type, array $payload, string $at = 'now'): array
    {
        return [
            'id' => $this->uuid(),
            'type' => $type,
            'at' => (new \DateTimeImmutable($at))->format(\DATE_RFC3339_EXTENDED),
            'payload' => $payload,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function pushOne(string $type, array $payload, string $at = 'now'): array
    {
        return $this->push([$this->mutation($type, $payload, $at)])[0];
    }

    /**
     * @param list<array<string, mixed>> $mutations
     *
     * @return list<array<string, mixed>>
     */
    private function push(array $mutations): array
    {
        $this->client->request('POST', '/api/sync/push', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'mealplanner',
        ], content: json_encode(['device_id' => 'test-device', 'mutations' => $mutations], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();

        return $this->json()['results'];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $this->client->request('GET', '/api/sync/snapshot');
        self::assertResponseIsSuccessful();

        return $this->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function pull(int $since, int $limit = 500): array
    {
        $this->client->request('GET', \sprintf('/api/sync/pull?since=%d&limit=%d', $since, $limit));
        self::assertResponseIsSuccessful();

        return $this->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function pulledEntity(int $since, string $type, string $id): array
    {
        $matches = array_filter($this->pull($since)['changes'], static fn (array $change): bool => $change['type'] === $type && $change['id'] === $id);
        self::assertNotEmpty($matches, \sprintf('%s %s fehlt im Pull.', $type, $id));

        return end($matches)['data'];
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
        self::fail(\sprintf('Eintrag %s fehlt im Snapshot.', $id));
    }

    /**
     * @return array<string, mixed>
     */
    private function article(string $name): array
    {
        foreach ($this->snapshot()['entities']['article'] as $article) {
            if ($article['name'] === $name) {
                return $article;
            }
        }
        self::fail(\sprintf('Artikel %s fehlt im Snapshot.', $name));
    }

    /**
     * @return array<string, mixed>
     */
    private function category(string $seedKey): array
    {
        foreach ($this->snapshot()['entities']['category'] as $category) {
            if ($category['seed_key'] === $seedKey) {
                return $category;
            }
        }
        self::fail(\sprintf('Kategorie %s fehlt im Snapshot.', $seedKey));
    }

    private function purchaseEvents(string $itemId): int
    {
        return (int) self::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM purchase_event WHERE item_id = ?', [$itemId]);
    }

    private function uuid(): string
    {
        return Uuid::v7()->toRfc4122();
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $data;
    }
}
