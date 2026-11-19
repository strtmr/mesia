<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\TextNormalizer;
use App\Catalog\Infrastructure\Seed\SeedCatalogReader;
use App\Catalog\Infrastructure\Seed\SeedCategory;
use PHPUnit\Framework\TestCase;

/**
 * Prüft die Seed-Dateien in data/seed/ auf Konsistenz.
 *
 * Läuft bei jedem Commit, damit beim Erweitern des Katalogs keine Dubletten entstehen.
 */
final class SeedCatalogTest extends TestCase
{
    /** @var list<SeedCategory> */
    private array $categories;

    protected function setUp(): void
    {
        $this->categories = (new SeedCatalogReader(__DIR__.'/../../../data/seed'))->read();
    }

    public function testCategoriesHaveUniqueKeysAndExactlyOneFallback(): void
    {
        $keys = array_map(static fn (SeedCategory $category): string => $category->key, $this->categories);
        self::assertSame(array_values(array_unique($keys)), $keys, 'Kategorie-Keys müssen eindeutig sein.');

        $fallbacks = array_filter($this->categories, static fn (SeedCategory $category): bool => $category->fallback);
        self::assertCount(1, $fallbacks, 'Es muss genau eine Fallback-Kategorie geben.');

        foreach ($this->categories as $category) {
            self::assertMatchesRegularExpression('/^[a-z_]+$/', $category->key);
            self::assertNotSame('', trim($category->name));
            self::assertNotSame('', trim($category->icon));
        }
    }

    /**
     * Jeder Name und jeder Alias muss nach der Normalisierung genau einmal vorkommen, auch ein Alias, der nur seinen
     * eigenen Namen wiederholt ("Creme fraiche" zu "Crème fraîche"), gilt als Dublette.
     */
    public function testNamesAndAliasesAreUniqueAfterNormalization(): void
    {
        $normalizer = new TextNormalizer();
        /** @var array<string, string> $owners normalisierter Begriff => Artikelname */
        $owners = [];
        $duplicates = [];
        $articleCount = 0;

        foreach ($this->categories as $category) {
            foreach ($category->articles as $article) {
                ++$articleCount;
                foreach ([$article->name, ...$article->aliases] as $term) {
                    $normalized = $normalizer->normalize($term);
                    self::assertNotSame('', $normalized, \sprintf('"%s" ist nach der Normalisierung leer.', $term));

                    if (isset($owners[$normalized])) {
                        $duplicates[] = \sprintf('"%s" (%s) kollidiert mit %s', $term, $article->name, $owners[$normalized]);
                    }
                    $owners[$normalized] = $article->name;
                }
            }
        }

        self::assertSame([], $duplicates);
        self::assertGreaterThan(300, $articleCount, 'Der Grundkatalog sollte mehr als 300 Artikel enthalten.');
    }
}
