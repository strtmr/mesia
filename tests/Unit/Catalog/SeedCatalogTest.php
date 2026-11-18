<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\TextNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Prüft die Seed-Dateien in data/seed/ auf Konsistenz.
 *
 * Läuft bei jedem Commit, damit beim Erweitern des Katalogs keine Dubletten entstehen.
 */
final class SeedCatalogTest extends TestCase
{
    private const string SEED_DIR = __DIR__.'/../../../data/seed';

    public function testCategoriesHaveUniqueKeysAndExactlyOneFallback(): void
    {
        $categories = $this->categories();

        $keys = array_column($categories, 'key');
        self::assertSame(array_unique($keys), $keys, 'Kategorie-Keys müssen eindeutig sein.');

        $fallbacks = array_filter($categories, static fn (array $category): bool => true === ($category['fallback'] ?? false));
        self::assertCount(1, $fallbacks, 'Es muss genau eine Fallback-Kategorie geben.');

        foreach ($categories as $category) {
            self::assertMatchesRegularExpression('/^[a-z_]+$/', $category['key']);
            self::assertNotSame('', trim($category['name']));
            self::assertNotSame('', trim($category['icon']));
        }
    }

    public function testArticlesReferenceExistingCategories(): void
    {
        $categoryKeys = array_column($this->categories(), 'key');

        foreach (array_keys($this->articlesByCategory()) as $categoryKey) {
            self::assertContains($categoryKey, $categoryKeys, \sprintf('Unbekannte Kategorie "%s" in articles.yaml.', $categoryKey));
        }
    }

    public function testNamesAndAliasesAreUniqueAfterNormalization(): void
    {
        $normalizer = new TextNormalizer();
        /** @var array<string, string> $owners normalisierter Begriff => Artikelname */
        $owners = [];
        $duplicates = [];

        foreach ($this->articlesByCategory() as $entries) {
            foreach ($entries as $entry) {
                [$name, $aliases] = self::parseEntry($entry);

                foreach ([$name, ...$aliases] as $term) {
                    $normalized = $normalizer->normalize($term);
                    self::assertNotSame('', $normalized, \sprintf('"%s" ist nach der Normalisierung leer.', $term));

                    if (isset($owners[$normalized]) && $owners[$normalized] !== $name) {
                        $duplicates[] = \sprintf('"%s" (%s) kollidiert mit %s', $term, $name, $owners[$normalized]);
                    }
                    $owners[$normalized] = $name;
                }
            }
        }

        self::assertSame([], $duplicates);
        self::assertGreaterThan(300, \count(array_unique($owners)), 'Der Grundkatalog sollte mehr als 300 Artikel enthalten.');
    }

    /**
     * @return array{string, list<string>}
     */
    private static function parseEntry(string $entry): array
    {
        $parts = explode('|', $entry, 2);
        $name = trim($parts[0]);
        $aliases = isset($parts[1])
            ? array_values(array_filter(array_map(trim(...), explode(',', $parts[1])), static fn (string $alias): bool => '' !== $alias))
            : [];

        return [$name, $aliases];
    }

    /**
     * @return list<array{key: string, name: string, icon: string, fallback?: bool}>
     */
    private function categories(): array
    {
        /** @var array{categories: list<array{key: string, name: string, icon: string, fallback?: bool}>} $data */
        $data = Yaml::parseFile(self::SEED_DIR.'/categories.yaml');

        return $data['categories'];
    }

    /**
     * @return array<string, list<string>>
     */
    private function articlesByCategory(): array
    {
        /** @var array{articles: array<string, list<string>>} $data */
        $data = Yaml::parseFile(self::SEED_DIR.'/articles.yaml');

        return $data['articles'];
    }
}
