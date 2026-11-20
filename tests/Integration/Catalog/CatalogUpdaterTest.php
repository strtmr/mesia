<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\CatalogSeeder;
use App\Catalog\Application\CatalogUpdater;
use App\Catalog\Domain\Model\Article;
use App\Catalog\Domain\TextNormalizer;
use App\Catalog\Infrastructure\Doctrine\ArticleAliasRepository;
use App\Catalog\Infrastructure\Doctrine\ArticleRepository;
use App\Catalog\Infrastructure\Doctrine\CategoryRepository;
use App\Catalog\Infrastructure\Seed\SeedCatalogReader;
use App\Shared\Infrastructure\Persistence\HouseholdWriteLock;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Katalog-Abgleich mit einem kleinen Test-Katalog: v1 wird angelegt, der Haushalt ändert einiges, dann kommt v2.
 */
final class CatalogUpdaterTest extends KernelTestCase
{
    private const string FIXTURES = __DIR__.'/../../fixtures/catalog-update';

    private EntityManagerInterface $entityManager;
    private ArticleRepository $articles;
    private Uuid $householdId;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->articles = $container->get(ArticleRepository::class);
        $this->householdId = Uuid::v7();

        $seeder = new CatalogSeeder(
            new SeedCatalogReader(self::FIXTURES.'/v1'),
            $container->get(TextNormalizer::class),
            $container->get(CategoryRepository::class),
            $this->entityManager,
            $container->get(ClockInterface::class),
        );
        $this->entityManager->wrapInTransaction(fn () => $seeder->seed($this->householdId));

        // Was der Haushalt seitdem selbst geändert hat
        $later = new \DateTimeImmutable('+1 hour');
        $other = $this->categoryBySeedKey('other');
        $this->entityManager->wrapInTransaction(function () use ($later, $other): void {
            $this->article('tomate')->rename('Strauchtomate', 'strauchtomate', $later);
            $this->article('milch')->moveToCategory($other, $later);
            $this->entityManager->persist(new Article(Uuid::v7(), $this->householdId, 'Hafersahne', 'hafersahne', $other, $later));
            $this->entityManager->persist(new Article(Uuid::v7(), $this->householdId, 'Falafel', 'falafel', $other, $later));
        });
    }

    public function testDryRunReportsButChangesNothing(): void
    {
        $report = $this->updater()->update($this->householdId, dryRun: true);

        self::assertSame([['Zwiebel', 'Zwiebeln'], ['Apfel', 'Äpfel']], $report->renamed);
        self::assertSame(['Laktosefreie Milch', 'Vegane Sahne'], $report->created);
        $this->entityManager->clear();
        self::assertSame('Zwiebel', $this->article('zwiebel')->getName());
        self::assertNull($this->articles->findByNormalizedTerm($this->householdId, 'laktosefreie milch'));
    }

    public function testRenamesSeedArticlesStillNamedLikeTheNewAlias(): void
    {
        $this->updater()->update($this->householdId);
        $this->entityManager->clear();

        self::assertSame('Zwiebeln', $this->article('zwiebel')->getName(), 'Einzahl wird zum Alias');
        self::assertSame('Zwiebeln', $this->article('zwiebeln')->getName());
        self::assertSame('Äpfel', $this->article('apfel')->getName());
        // Der bisherige Alias "Äpfel" ist jetzt der Name und als Alias entfernt
        self::assertNull(self::getContainer()->get(ArticleAliasRepository::class)->findActiveByNormalized($this->householdId, 'aepfel'));
    }

    public function testKeepsWhatTheHouseholdChanged(): void
    {
        $this->updater()->update($this->householdId);
        $this->entityManager->clear();

        // Selbst umbenannt: Name bleibt, neuer Seed-Name und Alias führen dorthin
        self::assertSame('Strauchtomate', $this->article('tomaten')->getName());
        self::assertSame('Strauchtomate', $this->article('tomate')->getName());
        // Selbst verschoben: Kategorie bleibt, neuer Alias kommt dazu
        self::assertTrue($this->article('vollmilch')->getCategoryId()->equals($this->categoryBySeedKey('other')));
        // Selbst angelegt mit einem Begriff, der im neuen Katalog Alias ist: bleibt eigenständig
        self::assertSame('Hafersahne', $this->article('hafersahne')->getName());
        self::assertSame('Vegane Sahne', $this->article('sojasahne')->getName());
        // Selbst angelegt mit genau dem Seed-Namen: derselbe Artikel, bekommt die Aliase
        self::assertSame('Falafel', $this->article('falafelbaellchen')->getName());
        self::assertCount(1, array_filter($this->articles->findAllActive($this->householdId), static fn (Article $article): bool => 'falafel' === $article->getNameNormalized()));
    }

    public function testCreatesNewArticlesInTheirSeedCategory(): void
    {
        $this->updater()->update($this->householdId);
        $this->entityManager->clear();

        $article = $this->article('milch laktosefrei');
        self::assertSame('Laktosefreie Milch', $article->getName());
        self::assertTrue($article->isFromSeed());
        self::assertTrue($article->getCategoryId()->equals($this->categoryBySeedKey('dairy')));
    }

    public function testSecondRunChangesNothing(): void
    {
        $this->updater()->update($this->householdId);

        self::assertTrue($this->updater()->update($this->householdId)->isEmpty());
    }

    private function updater(): CatalogUpdater
    {
        $container = self::getContainer();

        return new CatalogUpdater(
            new SeedCatalogReader(self::FIXTURES.'/v2'),
            $container->get(TextNormalizer::class),
            $container->get(CategoryRepository::class),
            $this->articles,
            $container->get(ArticleAliasRepository::class),
            $this->entityManager,
            $container->get(HouseholdWriteLock::class),
            $container->get(ClockInterface::class),
        );
    }

    private function article(string $term): Article
    {
        $article = $this->articles->findByNormalizedTerm($this->householdId, $term);
        self::assertNotNull($article, \sprintf('Kein Artikel für "%s"', $term));

        return $article;
    }

    private function categoryBySeedKey(string $key): Uuid
    {
        foreach (self::getContainer()->get(CategoryRepository::class)->findAllActive($this->householdId) as $category) {
            if ($key === $category->getSeedKey()) {
                return $category->getId();
            }
        }
        self::fail(\sprintf('Kategorie "%s" fehlt', $key));
    }
}
