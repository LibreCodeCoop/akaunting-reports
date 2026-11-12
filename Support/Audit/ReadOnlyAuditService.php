<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use App\Utilities\Reports;
use Modules\AkauntingReports\Reports\AuditInconsistencies;
use InvalidArgumentException;

/**
 * The only orchestration entry point for the report and widget. Every batch is
 * company-scoped. Native report ACL is required even for dashboard reads.
 */
final class ReadOnlyAuditService
{
    /**
     * @return array{findings:list<Occurrence>,scanned:int,truncated:bool}
     */
    public function inspect(int $companyId, string $currency, int $maxDocuments = 10000): array
    {
        if ($companyId <= 0 || $maxDocuments < 1 || $maxDocuments > 10000) {
            throw new InvalidArgumentException('Invalid audit scan request.');
        }
        if (user() === null || $companyId !== (int) company_id()
            || Reports::cannotRead(AuditInconsistencies::class)) {
            abort(403);
        }

        $reader = new DocumentSettlementReader();
        $rules = [new DocumentSettlementRule(), new CategoryIntegrityRule()];
        $fiscalAvailable = class_exists(FiscalReceiptRule::class)
            && class_exists(FiscalReceiptReader::class);
        if ($fiscalAvailable) {
            $rules[] = new FiscalReceiptRule();
        }
        $catalog = new Catalog($rules);
        $findings = [];
        $cursor = 0;
        $scanned = 0;

        while ($scanned < $maxDocuments) {
            $snapshot = $reader->read($companyId, $currency, $cursor, min(500, $maxDocuments - $scanned));
            $documents = $snapshot->rows('documents');
            if ($documents === []) {
                break;
            }
            $ids = array_map(static fn (array $document): int => (int) $document['id'], $documents);
            $cursor = max($ids);
            $scanned += count($documents);
            $sources = [
                'documents' => ['available' => true, 'rows' => $documents],
                'transactions' => ['available' => true, 'rows' => $snapshot->rows('transactions')],
                'categories' => ['available' => true, 'rows' => $snapshot->rows('categories')],
            ];
            if ($fiscalAvailable) {
                $sources['fiscal_receipts'] = (new FiscalReceiptReader())->read($companyId, $ids);
            }
            foreach (Engine::evaluate(new Snapshot($companyId, $currency, $sources), $catalog) as $item) {
                $findings[$item->id()] = $item; // Repeat availability notices only once.
            }
        }
        $truncated = $scanned >= $maxDocuments &&
            (new DocumentSettlementReader())->read($companyId, $currency, $cursor, 1)->rows('documents') !== [];
        ksort($findings, SORT_STRING);
        return [
            'findings' => array_values($findings),
            'scanned' => $scanned,
            'truncated' => $truncated,
        ];
    }
}
