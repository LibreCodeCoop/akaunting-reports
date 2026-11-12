<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class MoneyTest extends TestCase
{
    /** @return array<string, array{string, int}> */
    public static function decimalAmounts(): array
    {
        return [
            'positive cents' => ['2.53', 253],
            'negative cents' => ['-2.53', -253],
            'zero' => ['0.00', 0],
            'whole amount' => ['1234', 123400],
            'insignificant trailing zeros' => ['2.5300', 253],
        ];
    }

    #[DataProvider('decimalAmounts')]
    public function testDecimalAmountsAreConvertedToIntegerCentsWithoutRounding(
        string $amount,
        int $expectedCents
    ): void {
        self::assertSame($expectedCents, Money::cents($amount));
    }

    /** @return array<string, array{string}> */
    public static function invalidAmounts(): array
    {
        return [
            'fractional cent' => ['2.351'],
            'localized separator without conversion' => ['1,25'],
            'not numeric' => ['invalid'],
            'outside safe integer precision' => ['12345678901234567'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function testInvalidAmountsAreRejected(string $amount): void
    {
        $this->expectException(UnexpectedValueException::class);
        Money::cents($amount);
    }
}
