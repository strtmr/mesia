<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\TextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextNormalizerTest extends TestCase
{
    /**
     * Dieselben Fälle prüft tests/js/normalize.test.js für die JavaScript-Variante.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function sharedFixtureCases(): iterable
    {
        $json = file_get_contents(\dirname(__DIR__, 2).'/fixtures/normalize.json');
        self::assertIsString($json);

        /** @var list<array{input: string, expected: string}> $cases */
        $cases = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);

        foreach ($cases as $case) {
            yield json_encode($case['input'], \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR) => [$case['input'], $case['expected']];
        }
    }

    #[DataProvider('sharedFixtureCases')]
    public function testNormalize(string $input, string $expected): void
    {
        self::assertSame($expected, (new TextNormalizer())->normalize($input));
    }
}
