<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\Money;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class MoneyTest extends TestCase
{
    public function testDecimalAmountsAreConvertedToIntegerCentsWithoutRounding(): void
    {
        self::assertSame(253, Money::cents('2.53'));
        self::assertSame(-253, Money::cents('-2.53'));
        self::assertSame(0, Money::cents('0.00'));
        self::assertSame(123400, Money::cents('1234'));
    }

    public function testFractionalCentsAreRejected(): void
    {
        $this->expectException(UnexpectedValueException::class);
        Money::cents('2.351');
    }
}
