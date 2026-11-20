<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Application\ReleaseNotes;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Läuft bei jedem Commit: Die angezeigte Version und die Neuigkeiten dürfen nicht auseinanderlaufen.
 */
final class ReleaseNotesTest extends TestCase
{
    private const string ROOT = __DIR__.'/../../..';

    public function testNewestReleaseNoteMatchesTheAppVersion(): void
    {
        /** @var array{parameters: array{'app.version': string}} $services */
        $services = Yaml::parseFile(self::ROOT.'/config/services.yaml');
        $notes = (new ReleaseNotes(self::ROOT.'/data/release-notes.yaml'))->all();

        self::assertNotEmpty($notes);
        self::assertSame($services['parameters']['app.version'], $notes[0]->version, 'app.version (config/services.yaml) muss der obersten Version in data/release-notes.yaml entsprechen.');
    }

    public function testEntryWithUnquotedColonIsReportedClearly(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'release-notes');
        file_put_contents($file, "- version: 9.9.9\n  date: 2026-01-01\n  changes:\n    - Neu: etwas\n");

        try {
            $this->expectException(\UnexpectedValueException::class);
            $this->expectExceptionMessage('9.9.9');
            (new ReleaseNotes($file))->all();
        } finally {
            unlink($file);
        }
    }

    public function testVersionsAreOrderedNewestFirstAndHaveChanges(): void
    {
        $notes = (new ReleaseNotes(self::ROOT.'/data/release-notes.yaml'))->all();

        foreach ($notes as $index => $note) {
            self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $note->version);
            self::assertNotEmpty($note->changes, \sprintf('Version %s hat keine Änderungen.', $note->version));
            if ($index > 0) {
                $previous = $notes[$index - 1];
                self::assertTrue(version_compare($previous->version, $note->version, '>'), \sprintf('%s muss über %s stehen.', $previous->version, $note->version));
                self::assertGreaterThanOrEqual($note->date, $previous->date);
            }
        }
    }
}
