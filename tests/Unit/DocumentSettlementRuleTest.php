<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\Audit\{Catalog, DocumentSettlementRule, Engine, Occurrence, Snapshot};
use PHPUnit\Framework\TestCase;

final class DocumentSettlementRuleTest extends TestCase
{
    /** @param list<array<string,mixed>> $payments */
    private static function inspect(string $status, string $amount, array $payments): array
    {
        $documents = [[
            'id' => 5, 'company_id' => 3, 'type' => 'invoice', 'status' => $status,
            'amount' => $amount, 'currency_code' => 'BRL', 'competence' => '2026-11',
        ]];
        foreach ($payments as &$payment) {
            $payment += ['company_id' => 3, 'document_id' => 5,
                'type' => 'income', 'currency_code' => 'BRL'];
        }
        unset($payment);
        return Engine::evaluate(new Snapshot(3, 'BRL', [
            'documents' => ['available' => true, 'rows' => $documents],
            'transactions' => ['available' => true, 'rows' => $payments],
        ]), new Catalog([new DocumentSettlementRule()]));
    }

    public function testPaidDocumentWithoutLinkedTransactionIsSuspiciousNotProven(): void
    {
        $result = self::inspect('paid', '100.00', []);
        self::assertCount(1, $result);
        self::assertSame('paid_without_payment', $result[0]->caseKey);
        self::assertSame(Occurrence::SUSPECTED, $result[0]->classification);
    }

    public function testLegitimatePartialPaymentsDoNotGenerateFindings(): void
    {
        self::assertSame([], self::inspect('partial', '100.00', [['amount' => '40.00']]));
    }

    public function testDraftWithoutPaymentsAndUnpaidIssuedDocumentsRemainClear(): void
    {
        self::assertSame([], self::inspect('draft', '100.00', []));
        self::assertSame([], self::inspect('unpaid', '100.00', []));
    }

    public function testDraftWithPaymentAndOverpaymentAreReviewCases(): void
    {
        self::assertSame('draft_with_payment', self::inspect('draft', '10.00', [
            ['amount' => '1.00']])[0]->caseKey);
        self::assertSame('payment_exceeds_document', self::inspect('paid', '10.00', [
            ['amount' => '10.02']])[0]->caseKey);
    }

    public function testCancelledDocumentWithPaymentNeedsReviewNotAutomaticCancellation(): void
    {
        self::assertSame('cancelled_with_payment', self::inspect('cancelled', '10.00', [
            ['amount' => '10.00']])[0]->caseKey);
    }

    public function testUnknownPaymentDirectionDoesNotCountAsValidSettlement(): void
    {
        $found = self::inspect('paid', '10.00', [['amount' => '10.00', 'type' => 'expense']]);
        self::assertCount(1, $found);
        self::assertSame(Occurrence::INSUFFICIENT, $found[0]->classification);
    }

    public function testPaymentAmountsUseExactCents(): void
    {
        self::assertSame([], self::inspect('paid', '10.01', [['amount' => '10.01']]));
        self::assertSame('payment_exceeds_document', self::inspect('paid', '10.01', [
            ['amount' => '10.02']])[0]->caseKey);
    }

    public function testBadCurrencyAndInvalidFinancialPrecisionCannotConfirmMismatch(): void
    {
        self::assertSame(Occurrence::INSUFFICIENT,
            self::inspect('paid', '10.00', [['amount' => '10.00', 'currency_code' => 'USD']])[0]->classification);
        self::assertSame(Occurrence::INSUFFICIENT,
            self::inspect('paid', '10.001', [['amount' => '10.00']])[0]->classification);
    }
}
