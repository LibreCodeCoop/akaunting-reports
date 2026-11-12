<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

/**
 * Read-only comparison of independently reviewed accounting snapshots.
 * This does not create journal entries, documents or banking transactions.
 */
final class StatementReconciliation
{
    /**
     * @param array<string,mixed> $external
     * @param array<string,mixed> $internal
     * @param array<string,string> $mapping External account code => internal code
     * @return array{differences:list<array<string,mixed>>,unmapped:list<string>,mapping_errors:list<string>,status:string}
     */
    public static function compare(array $external, array $internal, array $mapping): array
    {
        $external = StatementSnapshot::normalize($external);
        $internal = StatementSnapshot::normalize($internal);
        foreach (['company_id', 'period_start', 'period_end', 'currency'] as $field) {
            if ($external[$field] !== $internal[$field]) {
                throw new \InvalidArgumentException("Snapshots have incompatible {$field}.");
            }
        }
        $externalAccounts = array_column($external['accounts'], null, 'code');
        $internalAccounts = array_column($internal['accounts'], null, 'code');
        $unmapped = [];
        $mappingErrors = [];
        $totals = [];
        foreach ($externalAccounts as $code => $account) {
            if (!array_key_exists($code, $mapping)) {
                $unmapped[] = $code;
                continue;
            }
            $target = $mapping[$code];
            if (!is_string($target) || !isset($internalAccounts[$target])) {
                $mappingErrors[] = $code;
                continue;
            }
            $totals[$target] ??= ['opening_cents' => 0, 'debit_cents' => 0, 'credit_cents' => 0, 'closing_cents' => 0];
            foreach ($totals[$target] as $field => $previous) {
                $totals[$target][$field] = $previous + $account[$field];
            }
        }
        sort($unmapped, SORT_STRING);
        sort($mappingErrors, SORT_STRING);
        $differences = [];
        foreach ($totals as $target => $totalsByField) {
            $delta = [];
            foreach ($totalsByField as $field => $sum) {
                $delta[$field] = $sum - $internalAccounts[$target][$field];
            }
            $differences[] = [
                'internal_code' => $target,
                'external' => $totalsByField,
                'internal' => array_intersect_key($internalAccounts[$target], $totalsByField),
                'delta' => $delta,
                'matches' => count(array_filter($delta)) === 0,
            ];
        }
        usort($differences, static fn (array $a, array $b): int => strcmp($a['internal_code'], $b['internal_code']));
        $complete = $unmapped === [] && $mappingErrors === [] && count($totals) === count($internalAccounts);
        $hasDifference = count(array_filter($differences, static fn (array $row): bool => !$row['matches'])) > 0;
        return [
            'differences' => $differences,
            'unmapped' => $unmapped,
            'mapping_errors' => $mappingErrors,
            'status' => !$complete ? 'incomplete' : ($hasDifference ? 'divergent' : 'matched'),
        ];
    }
}
