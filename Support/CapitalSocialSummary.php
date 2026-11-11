<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

final class CapitalSocialSummary
{
    /** @param list<array<string,mixed>> $movements
     * @return array{members:list<array<string,mixed>>,total_cents:int,pending:int}
     */
    public static function calculate(array $movements): array
    {
        $members = [];
        foreach ($movements as $row) {
            $key = (string) $row['identity'];
            $members[$key] ??= [
                'identity' => $key,
                'name' => (string) $row['name'],
                'contact_id' => (int) $row['contact_id'],
                'integralizado_cents' => 0,
                'devolvido_cents' => 0,
                'saldo_cents' => 0,
                'pending' => 0,
            ];
            if ($row['status'] !== 'confirmado') {
                ++$members[$key]['pending'];
                continue;
            }
            $amount = (int) $row['amount_cents'];
            if ($amount >= 0) {
                $members[$key]['integralizado_cents'] += $amount;
            } else {
                $members[$key]['devolvido_cents'] -= $amount;
            }
            $members[$key]['saldo_cents'] += $amount;
            if (!empty($row['reason'])) {
                ++$members[$key]['pending'];
            }
        }
        foreach ($members as &$member) {
            if ($member['saldo_cents'] < 0) {
                ++$member['pending'];
            }
        }
        unset($member);
        uasort($members, static fn(array $a,array $b): int => [$a['name'],$a['contact_id']] <=> [$b['name'],$b['contact_id']]);
        return [
            'members' => array_values($members),
            'total_cents' => array_sum(array_column($members, 'saldo_cents')),
            'pending' => array_sum(array_column($members, 'pending')),
        ];
    }
}
