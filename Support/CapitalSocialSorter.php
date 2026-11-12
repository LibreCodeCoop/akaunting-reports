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
            'pending' => trans('akaunting-reports::ui.pending_with_warnings'),
            'last_contribution_date' => trans('akaunting-reports::ui.last_contribution'),
            'saldo_cents' => trans('akaunting-reports::ui.balance'),
            'name' => trans('akaunting-reports::ui.member'),
            'direct_cents' => trans('akaunting-reports::ui.direct_contribution'),
            'retained_cents' => trans('akaunting-reports::ui.retained_production'),
            'devolvido_cents' => trans('akaunting-reports::ui.refunds'),
        ];
    }

    /** @return array{string,string} */
    public static function overviewDefaults(mixed $column, mixed $direction): array
    {
        return self::preference($column, $direction, 'pending', 'desc');
    }

    /** @return array{string,string} */
    public static function overviewSecondaryDefaults(mixed $column, mixed $direction): array
    {
        return self::preference($column, $direction, 'last_contribution_date', 'desc');
    }

    /** @return array{string,string} */
    public static function overviewTertiaryDefaults(mixed $column, mixed $direction): array
    {
        return self::preference($column, $direction, 'name', 'asc');
    }

    /** @return array{string,string} */
    private static function preference(
        mixed $column, mixed $direction, string $fallbackColumn, string $fallbackDirection
    ): array {
        $key = is_string($column) && in_array($column, self::OVERVIEW, true)
            ? $column : $fallbackColumn;
        $dir = is_string($direction) && in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction) : $fallbackDirection;

        return [$key, $dir];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{column:string,direction:string}> $additionalPreferences
     * @return list<array<string,mixed>>
     */
    public static function sort(
        array $rows, string $view, mixed $column, mixed $direction,
        mixed $preferredColumn = null, mixed $preferredDirection = null,
        array $additionalPreferences = []
    ): array {
        $allowed = $view === 'ledger' ? self::LEDGER : self::OVERVIEW;
        [$default, $defaultDirection] = $view === 'ledger'
            ? ['date', 'desc']
            : self::overviewDefaults($preferredColumn, $preferredDirection);
        $validColumn = is_string($column) && in_array($column, $allowed, true);
        $key = $validColumn ? $column : $default;
        $dir = $validColumn && is_string($direction) && in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction)
            : ($key === $default ? $defaultDirection : 'asc');

        $orders = [[$key, $dir]];
        if ($view === 'overview') {
            // A header click changes only the primary order; persisted secondary
            // and tertiary criteria continue resolving ties in the same order.
            $secondary = is_array($additionalPreferences[0] ?? null)
                ? $additionalPreferences[0] : [];
            $tertiary = is_array($additionalPreferences[1] ?? null)
                ? $additionalPreferences[1] : [];
            $tieBreakers = [
                self::overviewSecondaryDefaults(
                    $secondary['column'] ?? null, $secondary['direction'] ?? null
                ),
                self::overviewTertiaryDefaults(
                    $tertiary['column'] ?? null, $tertiary['direction'] ?? null
                ),
            ];
            $used = [$key => true];
            foreach ($tieBreakers as [$nextKey, $nextDir]) {
                if (!isset($used[$nextKey])) {
                    $orders[] = [$nextKey, $nextDir];
                    $used[$nextKey] = true;
                }
            }
        }

        usort($rows, static function (array $a, array $b) use ($view, $orders): int {
            foreach ($orders as [$sortKey, $sortDir]) {
                $diff = self::compare($a, $b, $sortKey, $sortDir);
                if ($diff !== 0) {
                    return $diff;
                }
            }

            if ($view === 'ledger') {
                // Preserve chronological/source tie-breaking in the movement ledger.
                return [(string) ($a['date'] ?? ''), (string) ($a['name'] ?? ''),
                        (string) ($a['source'] ?? ''), (int) ($a['id'] ?? 0)]
                    <=> [(string) ($b['date'] ?? ''), (string) ($b['name'] ?? ''),
                        (string) ($b['source'] ?? ''), (int) ($b['id'] ?? 0)];
            }

            // Always deterministic, even when a user configures duplicate criteria.
            return [(int) ($a['contact_id'] ?? 0), (string) ($a['identity'] ?? '')]
                <=> [(int) ($b['contact_id'] ?? 0), (string) ($b['identity'] ?? '')];
        });

        return $rows;
    }

    private static function compare(array $a, array $b, string $key, string $direction): int
    {
        if ($key === 'last_contribution_date') {
            // Missing contribution dates stay last in both directions.
            $missingA = empty($a[$key]);
            $missingB = empty($b[$key]);
            if ($missingA !== $missingB) {
                return $missingA ? 1 : -1;
            }
        }

        $left = self::value($a, $key);
        $right = self::value($b, $key);
        $diff = in_array($key, self::NUMERIC, true)
            ? ((int) $left <=> (int) $right)
            : strnatcasecmp((string) $left, (string) $right);

        if ($key === 'pending' && $diff === 0) {
            // The overview's review cell combines two distinct classifications.
            // A confirmed reservation is not a pending movement, but it must
            // appear before a completely clean member when review is sorted.
            $diff = (int) ($a['warnings'] ?? 0) <=> (int) ($b['warnings'] ?? 0);
        }

        return $direction === 'desc' ? -$diff : $diff;
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
