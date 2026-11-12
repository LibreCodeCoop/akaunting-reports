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
        $found = [];
        foreach ($snapshot->rows('fiscal_receipts') as $receipt) {
            if ((string) ($receipt['status'] ?? '') !== 'emitted') {
                continue; // Substituted and cancelled receipts are historical evidence.
            }
            $invoiceId = (int) ($receipt['invoice_id'] ?? 0);
            $receiptId = (int) ($receipt['id'] ?? 0);
            if ($receiptId <= 0 || $invoiceId <= 0 || !isset($invoices[$invoiceId])) {
                continue;
            }
            $invoice = $invoices[$invoiceId];
            $evidence = ['invoice_id' => $invoiceId];
            $sourceType = (string) ($receipt['snapshot_source'] ?? '');
            $sourceHashValid = ($receipt['snapshot_hash_valid'] ?? false) === true;
            if ($sourceType !== 'authorized_xml' || !$sourceHashValid
                || (string) ($invoice['currency_code'] ?? '') !== $snapshot->currencyCode) {
                $found[] = $this->finding($snapshot->companyId, $receiptId, 'unverifiable_fiscal_source',
                    Occurrence::INSUFFICIENT, 'info', $evidence);
                continue;
            }

            try {
                $gross = Money::cents((string) ($receipt['gross_service_value'] ?? ''));
                $invoiceTotal = Money::cents((string) ($invoice['amount'] ?? ''));
                if ($gross < 0 || $invoiceTotal < 0) {
                    throw new UnexpectedValueException('Negative amount.');
                }
            } catch (UnexpectedValueException) {
                $found[] = $this->finding($snapshot->companyId, $receiptId, 'unverifiable_fiscal_amount',
                    Occurrence::INSUFFICIENT, 'info', $evidence);
                continue;
            }
            if ($gross !== $invoiceTotal) {
                $found[] = $this->finding($snapshot->companyId, $receiptId, 'fiscal_gross_mismatch',
                    Occurrence::SUSPECTED, 'warning', [
                        ...$evidence, 'fiscal_gross_cents' => $gross, 'invoice_total_cents' => $invoiceTotal,
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
