<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use InvalidArgumentException;

/** Deterministic sorting and pagination over findings from a bounded read-only scan. */
final class FindingPage
{
    public const SORT_COLUMNS = ['severity', 'classification', 'rule', 'source', 'competence', 'guidance'];

    /** Sort columns offered by the native Akaunting report editor. */
    public static function sortOptions(): array
    {
        $options = [];
        foreach (self::SORT_COLUMNS as $column) {
            $options[$column] = trans('akaunting-reports::audit.' . $column);
        }
        return $options;
    }

    /** @return array{string,string} */
    public static function primaryDefaults(mixed $column, mixed $direction): array
    {
        return self::preference($column, $direction, 'severity', 'asc');
    }

    /** @return array{string,string} */
    public static function secondaryDefaults(mixed $column, mixed $direction): array
    {
        return self::preference($column, $direction, 'competence', 'desc');
    }

    /** @return array{string,string} */
    public static function tertiaryDefaults(mixed $column, mixed $direction): array
    {
        return self::preference($column, $direction, 'rule', 'asc');
    }

    /** @return array{string,string} */
    private static function preference(
        mixed $column, mixed $direction, string $fallbackColumn, string $fallbackDirection
    ): array {
        $key = is_string($column) && in_array($column, self::SORT_COLUMNS, true)
            ? $column : $fallbackColumn;
        $dir = is_string($direction) && in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction) : $fallbackDirection;

        return [$key, $dir];
    }

    /**
     * @param list<Occurrence> $findings
     * @param array{classification?:string,severity?:string,source?:string,rule?:string,year?:string} $filters
     * @param list<array{column:string,direction:string}> $additionalPreferences
     * @return array{all:list<Occurrence>,rows:list<Occurrence>,total:int,page:int,pages:int,per_page:int}
     */
    public static function select(
        array $findings, array $filters, int $page = 1, int $perPage = 50,
        string $sort = 'severity', string $direction = 'asc', array $additionalPreferences = []
    ): array
    {
        if ($page < 1 || $page > 10000 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid audit pagination.');
        }
        if (!in_array($sort, self::SORT_COLUMNS, true)
            || !in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Unsupported audit sort.');
        }
        $filtered = [];
        foreach ($findings as $finding) {
            if (!($finding instanceof Occurrence)) {
                throw new InvalidArgumentException('Expected an audit occurrence.');
            }
            if (($filters['classification'] ?? 'all') !== 'all'
                && $finding->classification !== $filters['classification']) {
                continue;
            }
            if (($filters['severity'] ?? 'all') !== 'all'
                && $finding->severity !== $filters['severity']) {
                continue;
            }
            if (($filters['source'] ?? 'all') !== 'all'
                && $finding->source !== $filters['source']) {
                continue;
            }
            if (($filters['rule'] ?? 'all') !== 'all'
                && $finding->ruleId !== $filters['rule']) {
                continue;
            }
            if (($filters['year'] ?? 'all') !== 'all'
                && !str_starts_with((string) $finding->competence, $filters['year'] . '-')) {
                continue;
            }
            $filtered[] = $finding;
        }
        $orders = [[$sort, $direction]];
        $used = [$sort => true];
        foreach ([0, 1] as $index) {
            $preference = $additionalPreferences[$index] ?? null;
            if (!is_array($preference)) {
                continue;
            }
            [$column, $dir] = $index === 0
                ? self::secondaryDefaults($preference['column'] ?? null, $preference['direction'] ?? null)
                : self::tertiaryDefaults($preference['column'] ?? null, $preference['direction'] ?? null);
            if (!isset($used[$column])) {
                $orders[] = [$column, $dir];
                $used[$column] = true;
            }
        }

        $severity = ['critical' => 0, 'warning' => 1, 'info' => 2];
        $assessment = ['confirmed' => 0, 'suspected' => 1, 'insufficient' => 2];
        usort($filtered, static function (Occurrence $a, Occurrence $b) use ($severity, $assessment, $orders): int {
            foreach ($orders as [$column, $dir]) {
                $comparison = match ($column) {
                    'severity' => $severity[$a->severity] <=> $severity[$b->severity],
                    'classification' => $assessment[$a->classification] <=> $assessment[$b->classification],
                    'rule' => strcmp($a->caseKey, $b->caseKey),
                    'source' => strcmp($a->source, $b->source) ?: strnatcmp($a->sourceId, $b->sourceId),
                    'competence' => strcmp((string) $a->competence, (string) $b->competence),
                    'guidance' => strcmp($a->guidanceKey, $b->guidanceKey),
                };
                if ($comparison !== 0) {
                    return $dir === 'desc' ? -$comparison : $comparison;
                }
            }
            return strcmp((string) $b->competence, (string) $a->competence)
                ?: strcmp($a->id(), $b->id());
        });

        $total = count($filtered);
        return [
            'all' => $filtered,
            'rows' => array_slice($filtered, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'per_page' => $perPage,
        ];
    }
}
