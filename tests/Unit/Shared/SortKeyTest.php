<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\SortKey;
use PHPUnit\Framework\TestCase;

final class SortKeyTest extends TestCase
{
    /**
     * Gleiche Werte wie generateNKeysBetween(null, null, n) der JS-Bibliothek fractional-indexing.
     */
    public function testSequenceMatchesFractionalIndexingFormat(): void
    {
        $keys = SortKey::sequence(64);

        self::assertSame(['a0', 'a1', 'a2'], \array_slice($keys, 0, 3));
        self::assertSame('a9', $keys[9]);
        self::assertSame('aA', $keys[10]);
        self::assertSame('aZ', $keys[35]);
        self::assertSame('aa', $keys[36]);
        self::assertSame('az', $keys[61]);
        self::assertSame('b00', $keys[62]);
        self::assertSame('b01', $keys[63]);
    }

    public function testKeysSortInByteOrder(): void
    {
        $keys = SortKey::sequence(200);
        $sorted = $keys;
        sort($sorted, \SORT_STRING);

        self::assertSame($keys, $sorted);
    }

    public function testValidation(): void
    {
        self::assertTrue(SortKey::isValid('a0'));
        self::assertTrue(SortKey::isValid('b0V'));
        self::assertFalse(SortKey::isValid(''));
        self::assertFalse(SortKey::isValid('0a'));
        self::assertFalse(SortKey::isValid('a 1'));
    }
}
