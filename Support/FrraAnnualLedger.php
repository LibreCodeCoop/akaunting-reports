<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Read-only, strictly company-scoped FRRA sources. Only an exact configured
 * document prefix and item name qualify; other bills cannot be inferred as FRRA.
 */
final class FrraAnnualLedger
{
    /**
     * @param array{production_category_id:int,annual_category_id:int,production_prefix:string,annual_prefix:string,item_name:string} $policy
     * @return array{
     *     entries:list<array<string,mixed>>,undated_count:int,invalid_amount_count:int,
     *     non_brl_count:int,excluded_status_count:int,
     *     unallocated_by_year:array<int,array{items:int,cents:int}>
     * }
     */
    public static function read(int $companyId, array $policy): array
    {
        if ($companyId <= 0 || $policy['production_category_id'] <= 0
            || $policy['annual_category_id'] <= 0
            || $policy['production_category_id'] === $policy['annual_category_id']
            || $policy['production_prefix'] === '' || $policy['annual_prefix'] === ''
            || $policy['item_name'] === '') {
            throw new RuntimeException('Invalid company-specific FRRA configuration.');
        }

        $rows = DB::table('documents as d')
            ->join('document_items as i', function ($join) {
                $join->on('i.document_id', '=', 'd.id')->on('i.company_id', '=', 'd.company_id');
            })
            ->leftJoin('contacts as c', function ($join) {
                $join->on('c.id', '=', 'd.contact_id')->on('c.company_id', '=', 'd.company_id');
            })
            ->where('d.company_id', $companyId)
            ->where('d.type', 'bill')
            ->whereIn('d.category_id', [$policy['production_category_id'], $policy['annual_category_id']])
            ->whereNull('d.deleted_at')
            ->whereNull('i.deleted_at')
            ->where('i.name', $policy['item_name'])
            ->select('i.id as item_id', 'i.total', 'i.name as item_name', 'i.description',
                'd.id as document_id', 'd.document_number', 'd.contact_id', 'd.contact_name',
                'd.category_id', 'd.status', 'd.currency_code', 'c.name as current_contact_name')
            ->orderBy('i.id')->get();

        $result = [
            'entries' => [], 'undated_count' => 0, 'invalid_amount_count' => 0,
            'non_brl_count' => 0, 'excluded_status_count' => 0, 'unallocated_by_year' => [],
        ];
        $allowedStatuses = ['draft', 'sent', 'viewed', 'partial', 'paid', 'overdue', 'unpaid'];

        foreach ($rows as $row) {
            $source = match (true) {
                (int) $row->category_id === $policy['production_category_id']
                    && str_starts_with((string) $row->document_number, $policy['production_prefix'])
                    => 'production',
                (int) $row->category_id === $policy['annual_category_id']
                    && str_starts_with((string) $row->document_number, $policy['annual_prefix'])
                    => 'annual',
                default => null,
            };
            if ($source === null || trim((string) $row->item_name) !== $policy['item_name']) {
                continue;
            }
            if (!in_array((string) $row->status, $allowedStatuses, true)) {
                ++$result['excluded_status_count'];
                continue;
            }
            // Exclude non-BRL items before both monthly allocation and
            // historical unallocated totals, which are displayed in BRL.
            if ($row->currency_code !== 'BRL') {
                ++$result['non_brl_count'];
                continue;
            }
            $competence = FrraAnnualMatrix::competence((string) $row->description);
            if ($competence === null) {
                ++$result['undated_count'];
                // A historical line may explicitly refer to several months.
                // Do not arbitrarily allocate that amount to a single month.
                if (preg_match('/20\d{2}/', (string) $row->description, $matches)) {
                    $year = (int) $matches[0];
                    try {
                        $amount = Money::cents((string) $row->total);
                        if ($amount >= 0) {
                            $result['unallocated_by_year'][$year] ??= ['items' => 0, 'cents' => 0];
                            ++$result['unallocated_by_year'][$year]['items'];
                            $result['unallocated_by_year'][$year]['cents'] += $amount;
                        }
                    } catch (\UnexpectedValueException) {
                        ++$result['invalid_amount_count'];
                    }
                }
                continue;
            }
            try {
                $cents = Money::cents((string) $row->total);
            } catch (\UnexpectedValueException) {
                ++$result['invalid_amount_count'];
                continue;
            }
            if ($cents < 0) {
                ++$result['invalid_amount_count'];
                continue;
            }

            $result['entries'][] = [
                'item_id' => (int) $row->item_id,
                'document_id' => (int) $row->document_id,
                'contact_id' => (int) $row->contact_id,
                'name' => (string) ($row->current_contact_name ?: $row->contact_name),
                'competence' => $competence,
                'source' => $source,
                'status' => (string) $row->status,
                'amount_cents' => $cents,
            ];
        }

        return $result;
    }
}
