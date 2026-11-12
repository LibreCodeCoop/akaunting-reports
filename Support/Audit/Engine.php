<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use LogicException;

/**
 * Pure evaluator; unavailable evidence produces an informational finding,
 * never a confirmed financial discrepancy.
 */
final class Engine
{
    /** @return list<Occurrence> */
    public static function evaluate(Snapshot $snapshot, Catalog $catalog): array
    {
        $result = [];
        $seen = [];
        foreach ($catalog->rules() as $rule) {
            $missing = array_values(array_filter(
                $rule->sources(),
                static fn (string $name): bool => !$snapshot->available($name)
            ));
            if ($missing !== []) {
                sort($missing, SORT_STRING);
                $findings = [new Occurrence(
                    companyId: $snapshot->companyId,
                    ruleId: $rule->id(),
                    ruleVersion: $rule->version(),
                    classification: Occurrence::INSUFFICIENT,
                    severity: 'info',
                    source: 'source',
                    sourceId: 'availability',
                    caseKey: 'source_unavailable',
                    competence: null,
                    evidence: ['missing_source_count' => count($missing)],
                    explanationKey: 'audit.source_unavailable',
                    guidanceKey: 'audit.check_source_availability',
                )];
            } else {
                $findings = $rule->inspect($snapshot);
            }
            foreach ($findings as $finding) {
                if (!$finding instanceof Occurrence
                    || $finding->companyId !== $snapshot->companyId
                    || $finding->ruleId !== $rule->id()
                    || $finding->ruleVersion !== $rule->version()
                    || isset($seen[$finding->id()])) {
                    throw new LogicException('Audit rule returned an invalid or duplicate finding.');
                }
                $seen[$finding->id()] = true;
                $result[] = $finding;
            }
        }
        usort($result, static fn (Occurrence $a, Occurrence $b): int => strcmp($a->id(), $b->id()));
        return $result;
    }
}
