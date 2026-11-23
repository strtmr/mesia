<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Application\UserRegistration;
use App\Identity\Domain\Model\User;
use App\Notification\Application\PushDispatcher;
use App\Notification\Application\PushResult;
use App\Notification\Infrastructure\Doctrine\PushSubscriptionRepository;
use App\Notification\Infrastructure\Push\InMemoryPushSender;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Mitteilungen: Abo verwalten, Sammel-Nachricht, wenn jemand anderes etwas auf die Liste setzt.
 */
final class PushNotificationTest extends WebTestCase
{
    use CreatesUsers;

    private const string ENDPOINT = 'https://push.example.test/abo/marco';

    private KernelBrowser $client;
    private User $marco;
    private User $anna;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->marco = self::createUser();
        $this->anna = self::getContainer()->get(UserRegistration::class)
            ->createUser($this->marco->getHousehold(), 'anna@example.com', 'Anna', self::PASSWORD);
    }

    public function testStatusTellsWhetherThisDeviceIsSubscribed(): void
    {
        $this->client->loginUser($this->marco);

        self::assertSame(['enabled' => true, 'public_key' => 'BTestPublicKey', 'subscribed' => false, 'frequency' => null], $this->pushStatus());

        $this->subscribe('quarter_hour');
        self::assertSame(['enabled' => true, 'public_key' => 'BTestPublicKey', 'subscribed' => true, 'frequency' => 'quarter_hour'], $this->pushStatus());

        $this->api('DELETE', '/api/push/subscription', ['endpoint' => self::ENDPOINT]);
        self::assertResponseStatusCodeSame(204);
        self::assertFalse($this->pushStatus()['subscribed']);
    }

    public function testWritingRequestsNeedTheApiHeader(): void
    {
        $this->client->loginUser($this->marco);
        $this->client->request('POST', '/api/push/subscription', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertResponseStatusCodeSame(403);
    }

    public function testNotifiesWhenSomeoneElseAddsItems(): void
    {
        $this->client->loginUser($this->marco);
        $this->subscribe();

        $this->client->loginUser($this->anna);
        $this->addItems(['Milch', 'Brot']);

        $sent = $this->dispatch();
        self::assertCount(1, $sent);
        self::assertSame(self::ENDPOINT, $sent[0]['endpoint']);
        self::assertSame('Einkaufsliste', $sent[0]['message']->title);
        self::assertSame('Anna hat Milch und Brot auf die Liste gesetzt.', $sent[0]['message']->body);

        self::assertCount(0, $this->dispatch(), 'Schon Gemeldetes kommt nicht noch einmal');
    }

    public function testOwnItemsDoNotNotify(): void
    {
        $this->client->loginUser($this->marco);
        $this->subscribe();
        $this->addItems(['Milch']);

        self::assertCount(0, $this->dispatch());
    }

    public function testLongListsAreShortened(): void
    {
        $this->client->loginUser($this->marco);
        $this->subscribe();
        $this->client->loginUser($this->anna);
        $this->addItems(['Milch', 'Brot', 'Butter', 'Eier', 'Käse']);

        self::assertSame('Anna hat Milch, Brot, Butter und 2 weitere auf die Liste gesetzt.', $this->dispatch()[0]['message']->body);
    }

    public function testQuarterHourCollectsUntilTheIntervalHasPassed(): void
    {
        $this->client->loginUser($this->marco);
        $this->subscribe('quarter_hour');
        $this->client->loginUser($this->anna);

        $this->addItems(['Milch']);
        self::assertCount(1, $this->dispatch());

        $this->addItems(['Brot']);
        self::assertCount(0, $this->dispatch(), 'Noch keine 15 Minuten vergangen: wird gesammelt');

        // Die letzte Nachricht liegt jetzt 16 Minuten zurück
        $subscription = self::getContainer()->get(PushSubscriptionRepository::class)->findByEndpoint(self::ENDPOINT);
        self::assertNotNull($subscription);
        $subscription->markSent($subscription->getLastSeq(), new \DateTimeImmutable('-16 minutes'));
        self::getContainer()->get('doctrine')->getManager()->flush();

        $sent = $this->dispatch();
        self::assertCount(1, $sent);
        self::assertSame('Anna hat Brot auf die Liste gesetzt.', $sent[0]['message']->body);
    }

    public function testExpiredSubscriptionsAreRemoved(): void
    {
        $this->client->loginUser($this->marco);
        $this->subscribe();
        $this->client->loginUser($this->anna);
        $this->addItems(['Milch']);

        $this->sender()->nextResult = PushResult::Gone;
        $this->dispatch();

        self::assertNull(self::getContainer()->get(PushSubscriptionRepository::class)->findByEndpoint(self::ENDPOINT));
    }

    // ---------------------------------------------------------------- Hilfsmethoden

    private function subscribe(string $frequency = 'immediate'): void
    {
        $this->api('POST', '/api/push/subscription', ['endpoint' => self::ENDPOINT, 'p256dh' => 'BKey', 'auth' => 'auth-token', 'frequency' => $frequency]);
        self::assertResponseStatusCodeSame(204);
    }

    /**
     * @return array<string, mixed>
     */
    private function pushStatus(): array
    {
        $this->client->request('GET', '/api/push?endpoint='.urlencode(self::ENDPOINT));
        self::assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Wie die App: item.add per Sync-Push als der angemeldete Benutzer.
     *
     * @param list<string> $names Artikel aus dem Startkatalog
     */
    private function addItems(array $names): void
    {
        $this->client->request('GET', '/api/sync/snapshot');
        $snapshot = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $listId = $snapshot['entities']['shopping_list'][0]['id'];
        $articles = array_column($snapshot['entities']['article'], null, 'name');

        $mutations = array_map(static fn (string $name): array => [
            'id' => Uuid::v7()->toRfc4122(),
            'type' => 'item.add',
            'at' => (new \DateTimeImmutable())->format(\DATE_RFC3339_EXTENDED),
            'payload' => [
                'item_id' => Uuid::v7()->toRfc4122(),
                'list_id' => $listId,
                'article_id' => $articles[$name]['id'],
                'name' => $name,
                'category_id' => $articles[$name]['category_id'],
                'sort_key' => 'a0',
                'contribution' => ['id' => Uuid::v7()->toRfc4122(), 'quantity' => null, 'unit' => null],
            ],
        ], $names);

        $this->api('POST', '/api/sync/push', ['device_id' => 'test', 'mutations' => $mutations]);
        self::assertResponseIsSuccessful();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function api(string $method, string $url, array $body): void
    {
        $this->client->request($method, $url, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'mesia'], content: json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * Ein Cron-Lauf. Liefert die dabei verschickten Nachrichten.
     *
     * @return list<array{endpoint: string, message: \App\Notification\Application\PushMessage}>
     */
    private function dispatch(): array
    {
        $sender = $this->sender();
        $sender->sent = [];
        self::getContainer()->get(PushDispatcher::class)->dispatch();

        return $sender->sent;
    }

    private function sender(): InMemoryPushSender
    {
        return self::getContainer()->get(InMemoryPushSender::class);
    }
}
