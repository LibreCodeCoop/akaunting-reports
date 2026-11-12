<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

use UnexpectedValueException;

final class Money
{
    public static function cents(string|int|float $value): int
    {
        $text = (string) $value;
        if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $text, $matches)) {
            throw new UnexpectedValueException('Invalid financial amount.');
        }
        $fraction = $matches[3] ?? '';
        if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
            throw new UnexpectedValueException('Financial amount exceeds cent precision.');
        }
        $integer = $matches[2];
        if (strlen($integer) > 16) {
            throw new UnexpectedValueException('Financial amount is out of range.');
        }
        $cents = (int) $integer * 100 + (int) substr(str_pad($fraction, 2, '0'), 0, 2);
        return ($matches[1] === '-') ? -$cents : $cents;
    }

}
