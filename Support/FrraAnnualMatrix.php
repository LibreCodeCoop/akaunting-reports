<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

/**
 * Pure, cent-exact annual FRRA matrix. Production documents have priority over
 * annual FRRA bills. A bill supplements only a competence absent from production.
 * This avoids counting the same reserve both at withholding and annual settlement.
 */
final class FrraAnnualMatrix
{
    private const MONTHS = 12;

    /** @param list<array<string,mixed>> $entries
     * @return array<string,mixed>
     */
    public static function build(array $entries, int $year): array
    {
        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException('Invalid annual reporting year.');
        }

        $groups = [];
        foreach ($entries as $entry) {
            $competence = (string) ($entry['competence'] ?? '');
            if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D', $competence, $parts)
                || (int) $parts[1] !== $year) {
                continue;
            }
            $contactId = (int) ($entry['contact_id'] ?? 0);
            $source = (string) ($entry['source'] ?? '');
            $month = (int) $parts[2];
            if ($contactId <= 0 || !in_array($source, ['production', 'annual'], true)) {
                continue;
            }
            $key = $contactId . ':' . $month;
            $groups[$key] ??= ['contact_id' => $contactId, 'name' => '',
                'month' => $month, 'production' => [], 'annual' => []];
            $groups[$key]['name'] = (string) ($entry['name'] ?? '');
            $groups[$key][$source][] = $entry;
        }

        $members = [];
        $monthlyTotals = array_fill(1, self::MONTHS, 0);
        $productionCents = 0;
        $annualCents = 0;
        $annualOnlyMonths = 0;
        $annualOverlapMonths = 0;
        $overlapDifferenceCents = 0;
        $draftDocumentItems = 0;
        $duplicateAnnualMonths = 0;

        foreach ($groups as $group) {
            $memberId = $group['contact_id'];
            $month = $group['month'];
            $members[$memberId] ??= [
                'contact_id' => $memberId,
                'name' => $group['name'],
                'months' => array_fill(1, self::MONTHS, 0),
                'counts' => array_fill(1, self::MONTHS, 0),
                'sources' => array_fill(1, self::MONTHS, ''),
                'total_cents' => 0,
                'production_cents' => 0,
                'annual_cents' => 0,
                'draft_items' => 0,
            ];
            $members[$memberId]['name'] = $group['name'];
            $fromProduction = self::sumCents($group['production']);
            $fromAnnual = self::sumCents($group['annual']);

            if ($group['production'] !== []) {
                $selected = $group['production'];
                $value = $fromProduction;
                $source = 'production';
                if ($group['annual'] !== []) {
                    ++$annualOverlapMonths;
                    $overlapDifferenceCents += abs($fromAnnual - $fromProduction);
                }
                $productionCents += $value;
                $members[$memberId]['production_cents'] += $value;
            } else {
                $selected = $group['annual'];
                $value = $fromAnnual;
                $source = 'annual';
                ++$annualOnlyMonths;
                $annualCents += $value;
                $members[$memberId]['annual_cents'] += $value;
                if (count($selected) > 1) {
                    ++$duplicateAnnualMonths;
                }
            }

            $drafts = count(array_filter(
                $selected, static fn (array $entry): bool => ($entry['status'] ?? '') === 'draft'
            ));
            $members[$memberId]['months'][$month] += $value;
            $members[$memberId]['counts'][$month] += count($selected);
            $members[$memberId]['sources'][$month] = $source;
            $members[$memberId]['total_cents'] += $value;
            $members[$memberId]['draft_items'] += $drafts;
            $draftDocumentItems += $drafts;
            $monthlyTotals[$month] += $value;
        }

        $members = array_values($members);
        usort($members, static fn (array $a, array $b): int =>
            strnatcasecmp($a['name'], $b['name'])
                ?: ($a['contact_id'] <=> $b['contact_id']));

        return [
            'year' => $year,
            'members' => $members,
            'members_count' => count($members),
            'months' => $monthlyTotals,
            'production_cents' => $productionCents,
            'annual_cents' => $annualCents,
            'total_cents' => array_sum($monthlyTotals),
            'annual_only_months' => $annualOnlyMonths,
            'annual_overlap_months' => $annualOverlapMonths,
            'overlap_difference_cents' => $overlapDifferenceCents,
            'duplicate_annual_months' => $duplicateAnnualMonths,
            'draft_items' => $draftDocumentItems,
        ];
    }

    /**
     * Members for the native year filter, derived from the same audited ledger
     * and competence rules used by the report totals. A login or HR record is
     * not required: historical FRRA must remain accessible after departure.
     *
     * @param list<array<string,mixed>> $entries
     * @return array<int,string>
     */
    public static function memberOptions(array $entries, int $year): array
    {
        $members = self::build($entries, $year)['members'];

        $labelCounts = [];
        foreach ($members as $member) {
            $label = trim((string) $member['name']);
            $label = $label !== '' ? $label : 'Contato sem nome';
            $labelCounts[mb_strtolower($label)] = ($labelCounts[mb_strtolower($label)] ?? 0) + 1;
        }

        $options = [];
        foreach ($members as $member) {
            $id = (int) $member['contact_id'];
            $label = trim((string) $member['name']);
            $label = $label !== '' ? $label : 'Contato sem nome';
            // Different contacts with the same name are not silently merged.
            if ($labelCounts[mb_strtolower($label)] > 1) {
                $label .= ' (cadastro #' . $id . ')';
            }
            $options[$id] = $label;
        }

        return $options;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function sumCents(array $rows): int
    {
        return array_sum(array_map(static fn (array $row): int => (int) $row['amount_cents'], $rows));
    }

    /** Parse the explicit accounting competence, never the bill's issue date. */
    public static function competence(?string $description): ?string
    {
        if (!is_string($description) || !preg_match(
            '/^Referente ao ano\/mês:\s*(20\d{2})-(0[1-9]|1[0-2])\s*$/Diu',
            trim($description), $match
        )) {
            return null;
        }
        return $match[1] . '-' . $match[2];
    }
}
