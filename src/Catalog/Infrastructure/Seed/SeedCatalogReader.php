<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Seed;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Liest den Startkatalog aus data/seed/categories.yaml und data/seed/articles.yaml.
 *
 * Format der Artikel: "Name" oder "Name | Alias1, Alias2".
 */
final readonly class SeedCatalogReader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/data/seed')]
        private string $seedDirectory,
    ) {
    }

    /**
     * Kategorien in Dateireihenfolge, jeweils mit ihren Artikeln.
     *
     * @return list<SeedCategory>
     */
    public function read(): array
    {
        /** @var array{categories: list<array{key: string, name: string, icon: string, fallback?: bool}>} $categoryData */
        $categoryData = Yaml::parseFile($this->seedDirectory.'/categories.yaml');
        /** @var array{articles: array<string, list<string>>} $articleData */
        $articleData = Yaml::parseFile($this->seedDirectory.'/articles.yaml');

        $unknown = array_diff(array_keys($articleData['articles']), array_column($categoryData['categories'], 'key'));
        if ([] !== $unknown) {
            throw new \UnexpectedValueException(\sprintf('Unbekannte Kategorie(n) in articles.yaml: %s', implode(', ', $unknown)));
        }

        return array_map(
            static fn (array $category): SeedCategory => new SeedCategory(
                key: $category['key'],
                name: $category['name'],
                icon: $category['icon'],
                fallback: $category['fallback'] ?? false,
                articles: array_map(self::parseArticle(...), $articleData['articles'][$category['key']] ?? []),
            ),
            $categoryData['categories'],
        );
    }

    private static function parseArticle(string $entry): SeedArticle
    {
        $parts = explode('|', $entry, 2);
        $aliases = isset($parts[1])
            ? array_values(array_filter(array_map(trim(...), explode(',', $parts[1])), static fn (string $alias): bool => '' !== $alias))
            : [];

        return new SeedArticle(trim($parts[0]), $aliases);
    }
}
