<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

final class CapitalSocialSummary
{
    /**
     * All money is stored as integer cents. A positive retention is capital
     * contributed by offsetting a production bill, not cash income.
     *
     * @param list<array<string,mixed>> $movements
     * @return array<string,mixed>
     */
    public static function calculate(array $movements): array
    {
        $members = [];
        $confirmed = 0;
        $pending = 0;
        $warnings = 0;

        foreach ($movements as $row) {
            $key = (string) $row['identity'];
            $members[$key] ??= [
                'identity' => $key,
                'name' => (string) $row['name'],
                'contact_id' => (int) $row['contact_id'],
                'integralizado_cents' => 0,
                'direct_cents' => 0,
                'retained_cents' => 0,
                'devolvido_cents' => 0,
                'last_contribution_date' => null,
                'saldo_cents' => 0,
                'pending' => 0,
                'warnings' => 0,
                'status_labels' => [],
            ];

            if ($row['status'] !== 'confirmado') {
                ++$members[$key]['pending'];
                ++$pending;
                $label = CapitalSocialReview::label($row);
                $members[$key]['status_labels'][$label] = ($members[$key]['status_labels'][$label] ?? 0) + 1;
                continue;
            }

            ++$confirmed;
            $amount = (int) $row['amount_cents'];
            $kind = (string) ($row['kind'] ?? ($amount >= 0 ? 'integralizacao' : 'devolucao'));
            if ($amount >= 0) {
                if ($amount > 0 && in_array($kind, ['integralizacao', 'retencao'], true)) {
                    // This is financial activity, not proof of active membership.
                    $date = (string) ($row['date'] ?? '');
                    if ($date !== '' && ($members[$key]['last_contribution_date'] === null
                        || strcmp($date, $members[$key]['last_contribution_date']) > 0)) {
                        $members[$key]['last_contribution_date'] = $date;
                    }
                }
                $members[$key]['integralizado_cents'] += $amount;
                if ($kind === 'retencao') {
                    $members[$key]['retained_cents'] += $amount;
                } else {
                    $members[$key]['direct_cents'] += $amount;
                }
            } else {
                $members[$key]['devolvido_cents'] -= $amount;
            }
            $members[$key]['saldo_cents'] += $amount;

            if (!empty($row['reason'])) {
                ++$members[$key]['warnings'];
                ++$warnings;
                $label = CapitalSocialReview::label($row);
                $members[$key]['status_labels'][$label] = ($members[$key]['status_labels'][$label] ?? 0) + 1;
            }
        }

        foreach ($members as &$member) {
            if ($member['saldo_cents'] < 0) {
                ++$member['warnings'];
                ++$warnings;
                $member['status_labels']['Saldo negativo'] = ($member['status_labels']['Saldo negativo'] ?? 0) + 1;
            }
        }
        unset($member);

        uasort($members, static fn (array $a, array $b): int =>
            [$a['name'], $a['contact_id']] <=> [$b['name'], $b['contact_id']]
        );
        $members = array_values($members);

        return [
            'members' => $members,
            'members_count' => count($members),
            'movement_count' => count($movements),
            'confirmed_count' => $confirmed,
            'pending_count' => $pending,
            'warnings_count' => $warnings,
            'direct_cents' => array_sum(array_column($members, 'direct_cents')),
            'retained_cents' => array_sum(array_column($members, 'retained_cents')),
            'devolvido_cents' => array_sum(array_column($members, 'devolvido_cents')),
            'total_cents' => array_sum(array_column($members, 'saldo_cents')),
            'pending' => array_sum(array_column($members, 'pending')),
        ];
    }
}
