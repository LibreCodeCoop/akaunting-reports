<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

use InvalidArgumentException;

/**
 * Computes an accounting equity position from explicitly mapped leaf accounts.
 * Never interprets a bank balance as subscribed or paid-in member capital.
 */
final class CapitalAccountingPosition
{
    /**
     * @param array<string,mixed> $statement A reviewed canonical statement.
     * @param array{subscribed:list<string>,unpaid:list<string>} $accountMapping
     * @return array{subscribed_cents:int,unpaid_cents:int,realized_cents:int,financial_ledger_cents:int,unexplained_cents:int,in_agreement:bool}
     */
    public static function calculate(array $statement, array $accountMapping, int $financialConfirmedCents): array
    {
        $statement = StatementSnapshot::normalize($statement);
        $accounts = array_column($statement['accounts'], null, 'code');
        foreach (['subscribed', 'unpaid'] as $role) {
            if (!isset($accountMapping[$role]) || !is_array($accountMapping[$role])
                || !array_is_list($accountMapping[$role]) || $accountMapping[$role] === []) {
                throw new InvalidArgumentException('A complete account-role mapping is required.');
            }
        }
        $seen = [];
        $totals = ['subscribed' => 0, 'unpaid' => 0];
        foreach ($totals as $role => $ignored) {
            foreach ($accountMapping[$role] as $code) {
                if (!is_string($code) || !isset($accounts[$code]) || isset($seen[$code])) {
                    throw new InvalidArgumentException('Unknown, duplicate or overlapping equity account.');
                }
                $seen[$code] = true;
                $totals[$role] += $accounts[$code]['closing_cents'];
            }
        }

        // Credit (subscribed capital) is negative; unpaid contra-capital is debit-positive.
        $subscribed = -$totals['subscribed'];
        $unpaid = $totals['unpaid'];
        if ($subscribed < 0 || $unpaid < 0 || $unpaid > $subscribed) {
            throw new InvalidArgumentException('Equity account sign conventions or mappings require review.');
        }
        $realized = $subscribed - $unpaid;
        $difference = $realized - $financialConfirmedCents;
        return [
            'subscribed_cents' => $subscribed,
            'unpaid_cents' => $unpaid,
            'realized_cents' => $realized,
            'financial_ledger_cents' => $financialConfirmedCents,
            'unexplained_cents' => $difference,
            'in_agreement' => $difference === 0,
        ];
    }
}
