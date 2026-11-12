<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

/** Conservative interpretations of the pre-existing capital report review codes. */
final class CapitalIntegrityRule implements Rule
{
    #[\Override]
    public function id(): string { return 'capital_integrity'; }

    #[\Override]
    public function version(): int { return 1; }

    #[\Override]
    public function sources(): array { return ['capital_entries']; }

    /** @return list<Occurrence> */
    #[\Override]
    public function inspect(Snapshot $snapshot): array
    {
        $reviewCases = [
            'capital_payment_excess' => Occurrence::SUSPECTED,
            'capital_document_link' => Occurrence::SUSPECTED,
            'capital_ambiguous_items' => Occurrence::SUSPECTED,
            'capital_unverified_currency' => Occurrence::INSUFFICIENT,
            'capital_ambiguous_classification' => Occurrence::INSUFFICIENT,
        ];
        $result = [];
        foreach ($snapshot->rows('capital_entries') as $entry) {
            $case = (string) ($entry['case_key'] ?? '');
            $source = (string) ($entry['source'] ?? '');
            $sourceId = (int) ($entry['source_id'] ?? 0);
            if (!isset($reviewCases[$case]) || $sourceId <= 0
                || !in_array($source, ['document', 'transaction'], true)) {
                continue;
            }
            $classification = $reviewCases[$case];
            $result[] = new Occurrence(
                $snapshot->companyId, $this->id(), $this->version(),
                $classification, $classification === Occurrence::SUSPECTED ? 'warning' : 'info',
                $source, (string) $sourceId, $case, null,
                ['candidate_cents' => max(0, (int) ($entry['candidate_cents'] ?? 0))],
                'audit.' . $case, 'audit.review_source'
            );
        }
        return $result;
    }
}
