<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use InvalidArgumentException;

/** Deterministic pagination over findings from a bounded read-only scan. */
final class FindingPage
{
    /**
     * @param list<Occurrence> $findings
     * @param array{classification?:string,severity?:string,source?:string,rule?:string,year?:string} $filters
     * @return array{all:list<Occurrence>,rows:list<Occurrence>,total:int,page:int,pages:int,per_page:int}
     */
    public static function select(array $findings, array $filters, int $page = 1, int $perPage = 50): array
    {
        if ($page < 1 || $page > 10000 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Invalid audit pagination.');
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
        $severity = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($filtered, static function (Occurrence $a, Occurrence $b) use ($severity): int {
            return ($severity[$a->severity] <=> $severity[$b->severity])
                ?: strcmp((string) $b->competence, (string) $a->competence)
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
