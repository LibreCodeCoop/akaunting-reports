<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

/**
 * Sort already-reconciled FRRA member totals without changing amounts or
 * competence. Accept only the keys exposed by the native report editor.
 */
final class FrraAnnualSorter
{
    /** @return array<string,string> */
    public static function options(): array
    {
        return [
            'name' => trans('akaunting-reports::ui.member'),
            'total_cents' => trans('akaunting-reports::ui.total_annual'),
            'production_cents' => trans('akaunting-reports::ui.retained_production'),
            'annual_cents' => trans('akaunting-reports::ui.annual_bill_supplement'),
        ];
    }

    /** @return array{string,string} */
    public static function defaults(mixed $column, mixed $direction): array
    {
        $column = is_string($column) && array_key_exists($column, self::options())
            ? $column : 'name';
        $direction = is_string($direction) && in_array($direction, ['asc', 'desc'], true)
            ? $direction : 'asc';

        return [$column, $direction];
    }

    /**
     * @param list<array<string,mixed>> $members
     * @return list<array<string,mixed>>
     */
    public static function sort(
        array $members, mixed $column, mixed $direction,
        mixed $preferredColumn = null, mixed $preferredDirection = null
    ): array {
        [$fallbackColumn, $fallbackDirection] = self::defaults($preferredColumn, $preferredDirection);
        $explicitColumn = is_string($column) && array_key_exists($column, self::options());
        $key = $explicitColumn ? $column : $fallbackColumn;
        $order = $explicitColumn && is_string($direction) && in_array($direction, ['asc', 'desc'], true)
            ? $direction : ($key === $fallbackColumn ? $fallbackDirection : 'asc');

        usort($members, static function (array $a, array $b) use ($key, $order): int {
            $comparison = $key === 'name'
                ? strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
                : ((int) ($a[$key] ?? 0) <=> (int) ($b[$key] ?? 0));
            if ($comparison !== 0) {
                return $order === 'desc' ? -$comparison : $comparison;
            }

            $byName = strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            return $byName !== 0 ? $byName : (int) ($a['contact_id'] ?? 0) <=> (int) ($b['contact_id'] ?? 0);
        });

        return $members;
    }
}
