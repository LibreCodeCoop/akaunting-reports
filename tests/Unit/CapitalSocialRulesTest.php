<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\CapitalSocialPolicy;
use Modules\AkauntingReports\Support\CapitalSocialRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CapitalSocialRulesTest extends TestCase
{
    private static function paidDocument(): array
    {
        return [
            'type' => 'invoice',
            'currency_code' => 'BRL',
            'document_amount_cents' => 10000,
            'paid_cents' => 10000,
            'capital_cents' => 2500,
            'capital_lines' => 1,
            'item_lines' => 1,
            'has_payment' => true,
        ];
    }

    public function testConfirmedPaymentsAndProductionRetentionHaveOppositeSigns(): void
    {
        $invoice = CapitalSocialRules::document(self::paidDocument(), 'BRL');
        $withheld = CapitalSocialRules::document([
            ...self::paidDocument(), 'type' => 'bill', 'capital_cents' => -2500
        ], 'BRL');
        self::assertSame(2500, $invoice['amount_cents']);
        self::assertSame(2500, $withheld['amount_cents']);
    }

    /** @return array<string, array{array<string,mixed>}> */
    public static function unconfirmedDocumentCases(): array
    {
        return [
            'unpaid' => [['paid_cents' => 0, 'has_payment' => false]],
            'partially paid' => [['paid_cents' => 3000]],
            'overpaid without matched deduction' => [['paid_cents' => 10100]],
            'cancelled' => [['status' => 'cancelled']],
        ];
    }

    #[DataProvider('unconfirmedDocumentCases')]
    public function testUnconfirmedDocumentsDoNotBecomeCapital(array $changes): void
    {
        $result = CapitalSocialRules::document([...self::paidDocument(), ...$changes], 'BRL');

        self::assertSame('revisao', $result['kind']);
        self::assertSame(0, $result['amount_cents']);
    }

    public function testUnknownTransactionsCannotBeClassifiedAsDirectCapital(): void
    {
        $policy = new CapitalSocialPolicy(companyId: 1, categoryId: 4, itemId: 8, currencyCode: 'BRL');
        $record = [
            'type' => 'income', 'amount' => '10.00', 'description' => 'Member share',
            'category_id' => 4, 'category_code' => '', 'currency_code' => 'BRL',
        ];
        self::assertSame(1000, CapitalSocialRules::transaction($record, $policy)['amount_cents']);
        self::assertSame('revisao', CapitalSocialRules::transaction([...$record,'category_id'=>12], $policy)['kind']);
        self::assertSame('revisao', CapitalSocialRules::transaction([...$record,'currency_code'=>'USD'], $policy)['kind']);
    }

    public function testCompanyCurrencyControlsTransactionAndDocumentEligibility(): void
    {
        $usd = new CapitalSocialPolicy(
            companyId: 1, categoryId: 4, itemId: 8, currencyCode: 'USD'
        );
        $entry = [
            'type' => 'income', 'amount' => '12.50', 'description' => 'Capital contribution',
            'category_id' => 4, 'category_code' => '', 'currency_code' => 'USD',
        ];
        self::assertSame(1250, CapitalSocialRules::transaction($entry, $usd)['amount_cents']);
        self::assertSame('revisao', CapitalSocialRules::transaction([
            ...$entry, 'currency_code' => 'BRL'
        ], $usd)['kind']);
        $usdDocument = [...self::paidDocument(), 'currency_code' => 'USD'];
        self::assertSame(2500, CapitalSocialRules::document($usdDocument, 'USD')['amount_cents']);
        self::assertSame('revisao', CapitalSocialRules::document(
            $usdDocument, 'BRL'
        )['kind']);
    }

    public function testPostPaymentDeductionRequiresMatchingFinancialEvidence(): void
    {
        $bill = [
            ...self::paidDocument(), 'type' => 'bill', 'status' => 'paid',
            'capital_cents' => -2500,
            'paid_cents' => 12000, 'post_payment_deduction_cents' => 2000,
        ];
        self::assertSame('retencao', CapitalSocialRules::document($bill, 'BRL')['kind']);
        self::assertSame(2500, CapitalSocialRules::document($bill, 'BRL')['amount_cents']);
        self::assertSame('revisao', CapitalSocialRules::document([
            ...$bill, 'post_payment_deduction_cents' => 1750
        ], 'BRL')['kind']);
    }
}
