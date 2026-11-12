<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Optional module reader. The NFS-e receipt has no company column; tenant
 * scope is established through the joined native accounting document.
 */
final class FiscalReceiptReader
{
    /** @return array{available:bool,rows:list<array<string,mixed>>} */
    public function read(int $companyId, array $invoiceIds): array
    {
        if ($companyId <= 0 || count($invoiceIds) > 500) {
            throw new InvalidArgumentException('Invalid fiscal audit batch.');
        }
        foreach ($invoiceIds as $id) {
            if (!is_int($id) || $id <= 0) {
                throw new InvalidArgumentException('Invalid invoice ID.');
            }
        }
        if (!Schema::hasTable('nfse_receipts')
            || !Schema::hasColumn('nfse_receipts', 'authorized_fiscal_snapshot')) {
            return ['available' => false, 'rows' => []];
        }
        if ($invoiceIds === []) {
            return ['available' => true, 'rows' => []];
        }
        $rows = [];
        foreach (DB::table('nfse_receipts as f')
            ->join('documents as d', static function ($join): void {
                $join->on('d.id', '=', 'f.invoice_id');
            })
            ->where('d.company_id', $companyId)
            ->where('d.type', 'invoice')->whereNull('d.deleted_at')
            ->whereIn('f.invoice_id', $invoiceIds)
            ->orderBy('f.id')
            ->select('f.id', 'f.invoice_id', 'f.status', 'f.authorized_fiscal_snapshot')
            ->get() as $row) {
            $snapshot = json_decode((string) ($row->authorized_fiscal_snapshot ?? ''), true);
            $hash = is_array($snapshot) ? ($snapshot['source_sha256'] ?? null) : null;
            $rows[] = [
                'company_id' => $companyId,
                'id' => (int) $row->id,
                'invoice_id' => (int) $row->invoice_id,
                'status' => (string) $row->status,
                'snapshot_source' => is_array($snapshot) ? (string) ($snapshot['source'] ?? '') : '',
                'snapshot_hash_valid' => is_string($hash) && preg_match('/^[0-9a-f]{64}$/D', $hash) === 1,
                'gross_service_value' => is_array($snapshot) ? $snapshot['gross_service_value'] ?? null : null,
            ];
        }
        return ['available' => true, 'rows' => $rows];
    }
}
