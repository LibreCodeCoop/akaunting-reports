<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

/**
 * Sort pre-aggregated report rows with Akaunting's native sort/direction query.
 * The database Sortable trait only supports Eloquent columns, not aggregate
 * values computed from the capital ledger. Use an explicit allowed list.
 */
final class CapitalSocialSorter
{
    private const OVERVIEW = ['name', 'direct_cents', 'retained_cents', 'devolvido_cents', 'saldo_cents', 'last_contribution_date', 'pending'];
    private const LEDGER = ['date', 'name', 'kind', 'amount_cents', 'running_balance_cents', 'status', 'source'];
    private const NUMERIC = ['direct_cents', 'retained_cents', 'devolvido_cents', 'saldo_cents', 'pending', 'amount_cents', 'running_balance_cents'];

    /** Valid options offered by the native Akaunting report editor. */
    public static function overviewOptions(): array
    {
        return [
            'pending' => 'Pendências',
            'last_contribution_date' => 'Última integralização',
            'saldo_cents' => 'Saldo',
            'name' => 'Cooperado',
            'direct_cents' => 'Integralização direta',
            'retained_cents' => 'Retenção na produção',
            'devolvido_cents' => 'Devoluções',
        ];
    }

    /** @return array{string,string} A safe, company-report-specific default. */
    public static function overviewDefaults(mixed $column, mixed $direction): array
    {
        $key = is_string($column) && in_array($column, self::OVERVIEW, true)
            ? $column : 'pending';
        $dir = is_string($direction) && in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction) : 'desc';

        return [$key, $dir];
    }

    /** @param list<array<string, mixed>> $rows
     *  @return list<array<string, mixed>>
     */
    public static function sort(
        array $rows, string $view, mixed $column, mixed $direction,
        mixed $preferredColumn = null, mixed $preferredDirection = null
    ): array {
        $allowed = $view === 'ledger' ? self::LEDGER : self::OVERVIEW;
        [$default, $defaultDirection] = $view === 'ledger'
            ? ['date', 'desc']
            : self::overviewDefaults($preferredColumn, $preferredDirection);
        $validColumn = is_string($column) && in_array($column, $allowed, true);
        $key = $validColumn ? $column : $default;
        // A malformed URL cannot change the configured fallback order.
        $dir = $validColumn && is_string($direction) && in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction)
            : ($key === $default ? $defaultDirection : 'asc');
        $factor = $dir === 'asc' ? 1 : -1;

        usort($rows, static function (array $a, array $b) use ($key, $factor): int {
            $left = self::value($a, $key);
            $right = self::value($b, $key);

            $diff = in_array($key, self::NUMERIC, true)
                ? ((int) $left <=> (int) $right)
                : strnatcasecmp((string) $left, (string) $right);
            if ($diff !== 0) {
                return $factor * $diff;
            }

            // Stable deterministic tie-breaks, independent of the current page.
            return [(string) ($a['date'] ?? ''), (string) ($a['name'] ?? ''), (string) ($a['source'] ?? ''), (int) ($a['id'] ?? $a['contact_id'] ?? 0)]
                <=> [(string) ($b['date'] ?? ''), (string) ($b['name'] ?? ''), (string) ($b['source'] ?? ''), (int) ($b['id'] ?? $b['contact_id'] ?? 0)];
        });
        return $rows;
    }

    private static function value(array $row, string $key): string|int
    {
        if ($key === 'amount_cents' && ($row['status'] ?? null) === 'revisar') {
            // The table displays pending candidates rather than zero.
            return (int) ($row['candidate_cents'] ?? 0);
        }
        return $row[$key] ?? (in_array($key, self::NUMERIC, true) ? 0 : '');
    }
}
