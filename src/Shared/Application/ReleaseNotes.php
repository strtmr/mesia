<?php

declare(strict_types=1);

namespace App\Shared\Application;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Neuigkeiten je Version aus data/release-notes.yaml (neueste zuerst), angezeigt unter „Mehr → Neuigkeiten“.
 */
final readonly class ReleaseNotes
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/data/release-notes.yaml')]
        private string $file,
    ) {
    }

    /**
     * @return list<ReleaseNote>
     */
    public function all(): array
    {
        /** @var list<array{version: string, date: int|string, changes: list<mixed>}> $entries */
        $entries = Yaml::parseFile($this->file);

        return array_map(
            static fn (array $entry): ReleaseNote => new ReleaseNote(
                (string) $entry['version'],
                // Die YAML-Komponente liest ein unquotiertes Datum als Unix-Zeitstempel
                \is_int($entry['date']) ? new \DateTimeImmutable('@'.$entry['date']) : new \DateTimeImmutable($entry['date']),
                self::changes((string) $entry['version'], $entry['changes']),
            ),
            $entries,
        );
    }

    /**
     * @param list<mixed> $changes
     *
     * @return list<string>
     */
    private static function changes(string $version, array $changes): array
    {
        foreach ($changes as $change) {
            if (!\is_string($change)) {
                // Typischer Fall: "- Text: mehr Text" ohne Anführungszeichen wird als Schlüssel gelesen
                throw new \UnexpectedValueException(\sprintf('Neuigkeiten %s: Ein Eintrag ist kein Text. Einträge in \'…\' setzen.', $version));
            }
        }

        /* @var list<string> $changes */
        return $changes;
    }
}
