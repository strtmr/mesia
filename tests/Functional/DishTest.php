<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Identity\Domain\Model\User;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tab „Gerichte“: anlegen, ansehen, bearbeiten, löschen, nur im eigenen Haushalt.
 */
final class DishTest extends WebTestCase
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

    public function testEmptyOverviewInvitesToCreateTheFirstDish(): void
    {
        $this->client->request('GET', '/gerichte');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty-state', 'Noch keine Gerichte');
        self::assertSelectorExists('nav.tabbar a[href="/gerichte"][aria-current="page"]');
    }

    public function testCreateWithJustAName(): void
    {
        $this->create(['dish[name]' => 'Pizza bestellen']);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Pizza bestellen');
        self::assertSelectorTextContains('main', 'Noch ohne Rezept');
    }

    public function testCreateWithRecipeAndShowIt(): void
    {
        $this->create([
            'dish[name]' => 'Lasagne',
            'dish[servings]' => '4',
            'dish[effort]' => 'normal',
            'dish[prepMinutes]' => '30',
            'dish[cookMinutes]' => '45',
            'dish[tags]' => 'Pasta, Ofen, pasta',
            'dish[ingredients]' => "500 g Hackfleisch\n\n1 Dose Tomaten\n  2 Zwiebeln  ",
            'dish[steps]' => "Zwiebeln würfeln.\n\nHackfleisch anbraten.",
            'dish[sourceUrl]' => 'www.example.com/lasagne',
        ]);
        $crawler = $this->client->followRedirect();

        self::assertSelectorTextContains('.dish-head__meta', 'normal · 75 min · Pasta, Ofen');
        self::assertSelectorTextContains('main', 'Zutaten · für 4 Portionen');
        self::assertSame(['500 g Hackfleisch', '1 Dose Tomaten', '2 Zwiebeln'], $crawler->filter('.dish-ingredients li')->each(static fn ($li): string => $li->text()));
        self::assertSame(['Zwiebeln würfeln.', 'Hackfleisch anbraten.'], $crawler->filter('.dish-steps li')->each(static fn ($li): string => $li->text()));
        self::assertSame('https://www.example.com/lasagne', $crawler->filter('a[rel~="noopener"]')->attr('href'));

        $this->client->request('GET', '/gerichte');
        self::assertSelectorTextContains('.list-group', 'Lasagne');
        self::assertSelectorExists('.chip[data-tag="pasta"]');
        self::assertSelectorExists('.chip[data-tag="ofen"]');
    }

    public function testValidation(): void
    {
        $this->create(['dish[name]' => '', 'dish[servings]' => '0', 'dish[sourceUrl]' => 'kein link', 'dish[ingredients]' => str_repeat('x', 201)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Bitte einen Namen eingeben.');
        self::assertSelectorTextContains('main', 'Zwischen 1 und 99 Portionen.');
        self::assertSelectorTextContains('main', 'Eine Zutat darf höchstens 200 Zeichen lang sein');
    }

    public function testEditKeepsTheFormFilledAndSaves(): void
    {
        $this->create(['dish[name]' => 'Chili', 'dish[ingredients]' => "500 g Hackfleisch\n1 Dose Bohnen", 'dish[tags]' => 'scharf']);
        $showUrl = (string) $this->client->getResponse()->headers->get('Location');

        $crawler = $this->client->request('GET', $showUrl.'/bearbeiten');
        self::assertSame("500 g Hackfleisch\n1 Dose Bohnen", $crawler->filter('textarea[name="dish[ingredients]"]')->text(normalizeWhitespace: false));
        self::assertSame('scharf', $crawler->filter('input[name="dish[tags]"]')->attr('value'));

        $this->client->submitForm('Speichern', ['dish[name]' => 'Chili sin Carne', 'dish[ingredients]' => "1 Dose Bohnen\n1 Dose Mais"]);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Chili sin Carne');
        self::assertSame(['1 Dose Bohnen', '1 Dose Mais'], $crawler->filter('.dish-ingredients li')->each(static fn ($li): string => $li->text()));
    }

    public function testDeleteHidesTheDish(): void
    {
        $this->create(['dish[name]' => 'Reste']);
        $showUrl = (string) $this->client->getResponse()->headers->get('Location');

        $this->client->request('GET', $showUrl.'/bearbeiten');
        $this->client->submitForm('Gericht löschen');
        self::assertResponseRedirects('/gerichte', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.notice--success', 'Reste');

        $this->client->request('GET', $showUrl);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDishesOfOtherHouseholdsAreInvisible(): void
    {
        $this->create(['dish[name]' => 'Geheimrezept']);
        $showUrl = (string) $this->client->getResponse()->headers->get('Location');

        $this->client->loginUser(self::createUser('anna@example.com', 'Anna', 'Familie Anna'));
        $this->client->request('GET', $showUrl);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', $showUrl.'/bearbeiten');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/gerichte');
        self::assertSelectorTextNotContains('main', 'Geheimrezept');
    }

    /**
     * @param array<string, string> $fields
     */
    private function create(array $fields): void
    {
        $this->client->request('GET', '/gerichte/neu');
        $this->client->submitForm('Anlegen', $fields);
    }
}
