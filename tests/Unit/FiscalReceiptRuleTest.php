<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\Audit\{Catalog, Engine, FiscalReceiptRule, Occurrence, Snapshot};
use PHPUnit\Framework\TestCase;

final class FiscalReceiptRuleTest extends TestCase
{
    private static function check(string $invoiceStatus, string $receiptStatus, string $gross,
        bool $verified = true): array
    {
        return Engine::evaluate(new Snapshot(1, 'BRL', [
            'documents' => ['available' => true, 'rows' => [[
                'company_id' => 1, 'id' => 4, 'type' => 'invoice',
                'status' => $invoiceStatus, 'currency_code' => 'BRL', 'amount' => '100.00',
            ]]],
            'fiscal_receipts' => ['available' => true, 'rows' => [[
                'company_id' => 1, 'id' => 9, 'invoice_id' => 4,
                'status' => $receiptStatus, 'snapshot_source' => 'authorized_xml',
                'snapshot_hash_valid' => $verified, 'gross_service_value' => $gross,
            ]]],
        ]), new Catalog([new FiscalReceiptRule()]));
    }

    public function testIssuedAndUnpaidInvoiceDoesNotImplyFinancialPayment(): void
    {
        self::assertSame([], self::check('unpaid', 'emitted', '100.00'));
        self::assertSame([], self::check('draft', 'emitted', '100.00'));
    }

    public function testFiscalDifferenceIsSuspectedNotConfirmed(): void
    {
        $finding = self::check('unpaid', 'emitted', '100.01')[0];
        self::assertSame(Occurrence::SUSPECTED, $finding->classification);
        self::assertSame('fiscal_gross_mismatch', $finding->caseKey);
        self::assertSame(10001, $finding->evidence['fiscal_gross_cents']);
    }

    public function testCancelledAndSubstitutedFiscalReceiptsAreNotActive(): void
    {
        self::assertSame([], self::check('paid', 'cancelled', '200.00'));
        self::assertSame([], self::check('paid', 'substituted', '200.00'));
    }

    public function testMissingAuthorizedSnapshotIsNotAConfirmedMismatch(): void
    {
        $result = self::check('paid', 'emitted', '100.00', false);
        self::assertSame(Occurrence::INSUFFICIENT, $result[0]->classification);
    }

    public function testInvalidCentPrecisionIsInsufficientEvidence(): void
    {
        self::assertSame(Occurrence::INSUFFICIENT,
            self::check('paid', 'emitted', '100.001')[0]->classification);
    }

    public function testOptionalFiscalSourceAbsenceIsAnInfoFinding(): void
    {
        $findings = Engine::evaluate(new Snapshot(1, 'BRL', [
            'documents' => ['available' => true, 'rows' => []],
            'fiscal_receipts' => ['available' => false, 'rows' => []],
        ]), new Catalog([new FiscalReceiptRule()]));
        self::assertSame(Occurrence::INSUFFICIENT, $findings[0]->classification);
        self::assertSame('info', $findings[0]->severity);
    }
}
