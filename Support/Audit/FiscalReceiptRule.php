<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use Modules\AkauntingReports\Support\Money;
use UnexpectedValueException;

/**
 * Compare only verifiably linked, active authorized fiscal receipts. Fiscal
 * issuance never establishes financial settlement; value differences are
 * review signals, not automatically established accounting errors.
 */
final class FiscalReceiptRule implements Rule
{
    #[\Override]
    public function id(): string { return 'nfse_gross_comparison'; }

    #[\Override]
    public function version(): int { return 1; }

    #[\Override]
    public function sources(): array { return ['documents', 'fiscal_receipts']; }

    /** @return list<Occurrence> */
    #[\Override]
    public function inspect(Snapshot $snapshot): array
    {
        $invoices = [];
        foreach ($snapshot->rows('documents') as $document) {
            if ((string) ($document['type'] ?? '') === 'invoice') {
                $invoices[(int) ($document['id'] ?? 0)] = $document;
            }
        }
        $groups = [];
        foreach ($snapshot->rows('fiscal_receipts') as $receipt) {
            // Several active NFS-e receipts may settle different parts of one invoice.
            if ((string) ($receipt['status'] ?? '') !== 'emitted') {
                continue;
            }
            $invoiceId = (int) ($receipt['invoice_id'] ?? 0);
            $receiptId = (int) ($receipt['id'] ?? 0);
            if ($receiptId > 0 && $invoiceId > 0 && isset($invoices[$invoiceId])) {
                $groups[$invoiceId][] = $receipt;
            }
        }

        $found = [];
        ksort($groups, SORT_NUMERIC);
        foreach ($groups as $invoiceId => $receipts) {
            usort($receipts, static fn (array $a, array $b): int =>
                ((int) $a['id']) <=> ((int) $b['id']));
            $firstReceiptId = (int) $receipts[0]['id'];
            $invoice = $invoices[$invoiceId];
            $evidence = ['invoice_id' => (int) $invoiceId, 'receipt_count' => count($receipts)];
            $unverifiableSource = (string) ($invoice['currency_code'] ?? '') !== $snapshot->currencyCode;
            $unverifiableAmount = false;
            $grossTotal = 0;
            foreach ($receipts as $receipt) {
                $unverifiableSource = $unverifiableSource
                    || (string) ($receipt['snapshot_source'] ?? '') !== 'authorized_xml'
                    || ($receipt['snapshot_hash_valid'] ?? false) !== true;
                if ($unverifiableSource) {
                    continue;
                }
                try {
                    $gross = Money::cents((string) ($receipt['gross_service_value'] ?? ''));
                    if ($gross < 0) {
                        throw new UnexpectedValueException('Negative fiscal amount.');
                    }
                    $grossTotal += $gross;
                } catch (UnexpectedValueException) {
                    $unverifiableAmount = true;
                }
            }
            if ($unverifiableSource) {
                $found[] = $this->finding($snapshot->companyId, $firstReceiptId,
                    'unverifiable_fiscal_source', Occurrence::INSUFFICIENT, 'info', $evidence);
                continue;
            }
            if ($unverifiableAmount) {
                $found[] = $this->finding($snapshot->companyId, $firstReceiptId,
                    'unverifiable_fiscal_amount', Occurrence::INSUFFICIENT, 'info', $evidence);
                continue;
            }
            try {
                $invoiceTotal = Money::cents((string) ($invoice['amount'] ?? ''));
                if ($invoiceTotal < 0) {
                    throw new UnexpectedValueException('Negative invoice amount.');
                }
            } catch (UnexpectedValueException) {
                $found[] = $this->finding($snapshot->companyId, $firstReceiptId,
                    'unverifiable_fiscal_amount', Occurrence::INSUFFICIENT, 'info', $evidence);
                continue;
            }
            if ($grossTotal !== $invoiceTotal) {
                $found[] = $this->finding($snapshot->companyId, $firstReceiptId,
                    'fiscal_gross_mismatch', Occurrence::SUSPECTED, 'warning', [
                        ...$evidence, 'fiscal_gross_cents' => $grossTotal,
                        'invoice_total_cents' => $invoiceTotal,
                    ]);
            }
        }
        return $found;
    }

    /** @param array<string,int|bool|string> $evidence */
    private function finding(
        int $companyId, int $id, string $case,
        string $classification, string $severity, array $evidence
    ): Occurrence {
        return new Occurrence(
            $companyId, $this->id(), $this->version(), $classification, $severity,
            'fiscal_receipt', (string) $id, $case, null, $evidence,
            'audit.' . $case, 'audit.review_source'
        );
    }
}
