<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Read-only, bounded batch adapter. Call ONLY after native per-report permission
 * and active-company checks have passed. SQL never reads another company's rows.
 */
final class DocumentSettlementReader
{
    public function read(int $companyId, string $currencyCode, int $afterId = 0, int $limit = 200): Snapshot
    {
        if ($companyId <= 0 || $afterId < 0 || $limit < 1 || $limit > 500) {
            throw new InvalidArgumentException('Invalid audit batch request.');
        }

        $documents = [];
        $ids = [];
        $query = DB::table('documents')
            ->where('company_id', $companyId)->whereNull('deleted_at')
            ->whereIn('type', ['invoice', 'bill'])->where('id', '>', $afterId)
            ->orderBy('id')->limit($limit)
            ->select('id', 'company_id', 'type', 'status', 'currency_code', 'amount', 'issued_at');
        foreach ($query->get() as $document) {
            $id = (int) $document->id;
            $date = substr((string) ($document->issued_at ?? ''), 0, 7);
            $documents[] = [
                'company_id' => $companyId, 'id' => $id, 'type' => (string) $document->type,
                'status' => (string) $document->status,
                'currency_code' => (string) $document->currency_code,
                'amount' => (string) $document->amount,
                'competence' => preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D', $date) ? $date : null,
            ];
            $ids[] = $id;
        }

        $transactions = [];
        if ($ids !== []) {
            foreach (DB::table('transactions')
                ->where('company_id', $companyId)->whereNull('deleted_at')
                ->whereIn('document_id', $ids)->orderBy('id')
                ->select('id', 'company_id', 'document_id', 'type', 'currency_code', 'amount')
                ->get() as $payment) {
                $transactions[] = [
                    'company_id' => $companyId, 'id' => (int) $payment->id,
                    'document_id' => (int) $payment->document_id, 'type' => (string) $payment->type,
                    'currency_code' => (string) $payment->currency_code, 'amount' => (string) $payment->amount,
                ];
            }
        }

        return new Snapshot($companyId, $currencyCode, [
            'documents' => ['available' => true, 'rows' => $documents],
            'transactions' => ['available' => true, 'rows' => $transactions],
        ]);
    }
}
