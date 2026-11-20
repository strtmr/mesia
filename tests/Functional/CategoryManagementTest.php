<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use App\Identity\Domain\Model\User;
use App\Tests\Support\CreatesUsers;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * „Mehr → Kategorien“: anlegen, umbenennen, sortieren, löschen.
 */
final class CategoryManagementTest extends WebTestCase
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

    public function testListShowsCategoriesInStoreOrder(): void
    {
        $this->client->request('GET', '/mehr');
        $this->client->clickLink('Kategorien');

        self::assertResponseIsSuccessful();
        $names = $this->categoryNamesOnPage();
        self::assertCount(17, $names);
        self::assertSame('Obst & Gemüse', $names[0]);
        self::assertSame('Sonstiges', $names[16]);
        self::assertSelectorExists('nav.tabbar a[aria-current="page"][href="/mehr"]');
    }

    public function testNewCategoryIsPlacedBeforeTheFallback(): void
    {
        $this->client->request('GET', '/mehr/kategorien/neu');
        $this->client->submitForm('Anlegen', ['category[icon]' => '🥢', 'category[name]' => 'Asiatisch']);

        self::assertResponseRedirects('/mehr/kategorien', 303);
        $this->client->followRedirect();
        $names = $this->categoryNamesOnPage();
        self::assertSame(['Asiatisch', 'Sonstiges'], \array_slice($names, -2));
        self::assertGreaterThan(0, $this->changeLogCount('category'), 'Neue Kategorie steht im Änderungsprotokoll');
    }

    public function testDuplicateNameIsRejected(): void
    {
        $this->client->request('GET', '/mehr/kategorien/neu');
        $this->client->submitForm('Anlegen', ['category[icon]' => '🍎', 'category[name]' => 'obst & gemüse']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error', 'Es gibt schon eine Kategorie „Obst & Gemüse“');
    }

    public function testRenameAndChangeIcon(): void
    {
        $this->client->request('GET', '/mehr/kategorien');
        $this->client->clickLink('Getränke');
        $this->client->submitForm('Speichern', ['category[icon]' => '💧', 'category[name]' => 'Getränke & Wasser']);

        self::assertResponseRedirects('/mehr/kategorien', 303);
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('💧', $crawler->filter('.category-row')->eq(13)->text());
        self::assertContains('Getränke & Wasser', $this->categoryNamesOnPage());
    }

    public function testReorderSavesTheNewOrder(): void
    {
        $crawler = $this->client->request('GET', '/mehr/kategorien');
        $list = $crawler->filter('[data-controller="category-sort"]');
        $ids = $crawler->filter('.category-row')->each(static fn ($row): string => (string) $row->attr('data-id'));
        $token = (string) $list->attr('data-category-sort-token-value');

        $this->reorder(array_reverse($ids), $token);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/mehr/kategorien');
        self::assertSame('Sonstiges', $this->categoryNamesOnPage()[0]);

        $this->reorder(array_reverse($ids), 'falsches-token');
        self::assertResponseStatusCodeSame(403);

        $this->reorder(\array_slice($ids, 1), $token);
        self::assertResponseStatusCodeSame(409, 'Unvollständige Liste (z. B. Kategorie inzwischen auf dem anderen Gerät angelegt)');
    }

    public function testDeleteMovesArticlesToTheFallback(): void
    {
        $householdId = $this->user->getHousehold()->getId();
        $juice = self::getContainer()->get(ArticleRepository::class)->findByNormalizedTerm($householdId, 'apfelsaft');
        self::assertNotNull($juice);

        $this->client->request('GET', '/mehr/kategorien');
        $this->client->clickLink('Getränke');
        $this->client->submitForm('Kategorie löschen');

        self::assertResponseRedirects('/mehr/kategorien', 303);
        $this->client->followRedirect();
        self::assertNotContains('Getränke', $this->categoryNamesOnPage());

        $fallback = self::getContainer()->get(CategoryRepository::class)->findFallback($householdId);
        $juice = self::getContainer()->get(ArticleRepository::class)->findByNormalizedTerm($householdId, 'apfelsaft');
        self::assertNotNull($fallback);
        self::assertTrue($juice?->getCategoryId()->equals($fallback->getId()));
    }

    public function testFallbackCannotBeDeleted(): void
    {
        $this->client->request('GET', '/mehr/kategorien');
        $this->client->clickLink('Getränke');
        $token = $this->client->getCrawler()->filter('form.category-delete input[name="_token"]')->attr('value');

        $this->client->request('GET', '/mehr/kategorien');
        $crawler = $this->client->clickLink('Sonstiges');
        self::assertSelectorNotExists('form.category-delete');
        $editUrl = $crawler->getUri();

        $this->client->request('POST', $editUrl.'/loeschen', ['_token' => $token]);
        self::assertResponseRedirects('/mehr/kategorien', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.form-error', 'kann nicht gelöscht werden');
        self::assertContains('Sonstiges', $this->categoryNamesOnPage());
    }

    public function testCategoriesOfOtherHouseholdsAreNotFound(): void
    {
        $crawler = $this->client->request('GET', '/mehr/kategorien');
        $foreignUrl = $crawler->filter('.category-row__link')->first()->link()->getUri();

        $this->client->loginUser(self::createUser('fremd@example.com', 'Fremd', 'Andere Familie'));
        $this->client->request('GET', $foreignUrl);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return list<string>
     */
    private function categoryNamesOnPage(): array
    {
        return $this->client->getCrawler()->filter('.category-row__name')->each(static fn ($node): string => $node->text());
    }

    /**
     * @param list<string> $ids
     */
    private function reorder(array $ids, string $token): void
    {
        $this->client->request('POST', '/mehr/kategorien/reihenfolge', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['ids' => $ids, 'token' => $token], \JSON_THROW_ON_ERROR));
    }

    private function changeLogCount(string $entityType): int
    {
        return (int) self::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM change_log WHERE household_id = ? AND entity_type = ?',
            [$this->user->getHousehold()->getId()->toRfc4122(), $entityType],
        );
    }
}
