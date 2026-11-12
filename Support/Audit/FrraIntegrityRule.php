<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

/** Conservative comparison by actual FRA competence, not bank settlement. */
final class FrraIntegrityRule implements Rule
{
    #[\Override]
    public function id(): string { return 'frra_integrity'; }

    #[\Override]
    public function version(): int { return 1; }

    #[\Override]
    public function sources(): array { return ['frra_entries']; }

    /** @return list<Occurrence> */
    #[\Override]
    public function inspect(Snapshot $snapshot): array
    {
        $groups = [];
        foreach ($snapshot->rows('frra_entries') as $entry) {
            $member = (int) ($entry['contact_id'] ?? 0);
            $document = (int) ($entry['document_id'] ?? 0);
            $competence = (string) ($entry['competence'] ?? '');
            $kind = (string) ($entry['source'] ?? '');
            $cents = $entry['amount_cents'] ?? null;
            if ($member <= 0 || $document <= 0
                || preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D', $competence) !== 1
                || !in_array($kind, ['production', 'annual'], true)
                || !is_int($cents) || $cents < 0) {
                continue;
            }
            $key = $member . ':' . $competence;
            $groups[$key] ??= [
                'competence' => $competence,
                'production' => 0, 'annual' => 0,
                'production_documents' => [], 'annual_documents' => [],
            ];
            $groups[$key][$kind] += $cents;
            $groups[$key][$kind . '_documents'][$document] = true;
        }

        ksort($groups, SORT_STRING);
        $result = [];
        foreach ($groups as $group) {
            $production = array_keys($group['production_documents']);
            $annual = array_keys($group['annual_documents']);
            sort($annual, SORT_NUMERIC);
            if ($annual === []) {
                continue;
            }
            $case = null;
            if ($production !== [] && $group['production'] !== $group['annual']) {
                $case = 'frra_overlap_mismatch';
            } elseif ($production === [] && count($annual) > 1) {
                $case = 'frra_duplicate_annual';
            }
            if ($case === null) {
                continue;
            }
            $result[] = new Occurrence(
                $snapshot->companyId, $this->id(), $this->version(),
                Occurrence::SUSPECTED, 'warning', 'document', (string) $annual[0],
                $case, $group['competence'], [
                    'production_cents' => $group['production'],
                    'annual_cents' => $group['annual'],
                    'production_documents' => count($production),
                    'annual_documents' => count($annual),
                ],
                'audit.' . $case, 'audit.review_source'
            );
        }
        return $result;
    }
}
