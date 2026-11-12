<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use Illuminate\Support\Facades\DB;
use Modules\AkauntingReports\Support\FrraAnnualLedger;

/** Read-only adapter to the configured FRA Annual source. */
final class FrraIntegrityReader
{
    /** @return array{available:bool,rows:list<array<string,mixed>>} */
    public function read(int $companyId, string $currencyCode): array
    {
        if ($companyId <= 0 || preg_match('/^[A-Z]{3}$/D', $currencyCode) !== 1) {
            throw new \InvalidArgumentException('Invalid FRA audit context.');
        }
        $prefix = 'akaunting-reports.frra.';
        $keys = [
            'production_category_id', 'annual_category_id',
            'production_prefix', 'annual_prefix', 'item_name',
        ];
        $values = DB::table('settings')->where('company_id', $companyId)
            ->whereIn('key', array_map(static fn (string $key): string => $prefix . $key, $keys))
            ->pluck('value', 'key');
        $policy = [
            'production_category_id' => (int) ($values[$prefix . 'production_category_id'] ?? 0),
            'annual_category_id' => (int) ($values[$prefix . 'annual_category_id'] ?? 0),
            'production_prefix' => (string) ($values[$prefix . 'production_prefix'] ?? ''),
            'annual_prefix' => (string) ($values[$prefix . 'annual_prefix'] ?? ''),
            'item_name' => (string) ($values[$prefix . 'item_name'] ?? ''),
            'currency_code' => $currencyCode,
        ];
        if ($policy['production_category_id'] <= 0 || $policy['annual_category_id'] <= 0
            || $policy['production_category_id'] === $policy['annual_category_id']
            || $policy['production_prefix'] === '' || $policy['annual_prefix'] === ''
            || $policy['production_prefix'] === $policy['annual_prefix']
            || $policy['item_name'] === '') {
            return ['available' => false, 'rows' => []];
        }

        // Reuse verified company-scoped FRA source; project only opaque IDs and cents.
        $ledger = FrraAnnualLedger::read($companyId, $policy);
        $rows = [];
        foreach ($ledger['entries'] as $item) {
            $rows[] = [
                'company_id' => $companyId,
                'document_id' => (int) $item['document_id'],
                'item_id' => (int) $item['item_id'],
                'contact_id' => (int) $item['contact_id'],
                'competence' => (string) $item['competence'],
                'source' => (string) $item['source'],
                'amount_cents' => (int) $item['amount_cents'],
            ];
        }
        return ['available' => true, 'rows' => $rows];
    }
}
