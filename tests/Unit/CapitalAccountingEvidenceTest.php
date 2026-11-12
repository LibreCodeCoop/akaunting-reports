<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use InvalidArgumentException;
use Modules\AkauntingReports\Support\CapitalAccountingPosition;
use Modules\AkauntingReports\Support\CapitalAssemblyEvidence;
use PHPUnit\Framework\TestCase;

final class CapitalAccountingEvidenceTest extends TestCase
{
    /** @return array<string,mixed> */
    private function statement(): array
    {
        return [
            'schema_version' => 1, 'company_id' => 22,
            'statement_id' => 'synthetic-equity', 'revision' => 'v1',
            'period_start' => '2025-01-01', 'period_end' => '2025-12-31',
            'currency' => 'BRL', 'source_sha256' => str_repeat('d', 64),
            'accounts' => [
                ['code' => 'A', 'name' => 'Subscribed class A', 'opening_cents' => -80000,
                    'debit_cents' => 0, 'credit_cents' => 20000, 'closing_cents' => -100000],
                ['code' => 'B', 'name' => 'Subscribed class B', 'opening_cents' => -40000,
                    'debit_cents' => 0, 'credit_cents' => 10000, 'closing_cents' => -50000],
                ['code' => 'C', 'name' => 'Unpaid capital contra-equity', 'opening_cents' => 70000,
                    'debit_cents' => 0, 'credit_cents' => 10000, 'closing_cents' => 60000],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function allocation(): array
    {
        return [
            'company_id' => 22, 'contact_id' => 101,
            'deliberation_ref' => 'AGO-2025', 'source_revision' => 'v1',
            'allocation_ref' => 'allocation-1', 'source_sha256' => str_repeat('e', 64),
            'kind' => 'capitalization', 'amount_cents' => 2500,
            'review_state' => 'pending_review', 'accounting_entry_ref' => null,
        ];
    }

    public function testGrossUnpaidAndRealizedCapitalStaySeparateFromFinancialMovements(): void
    {
        $result = CapitalAccountingPosition::calculate($this->statement(), [
            'subscribed' => ['A', 'B'], 'unpaid' => ['C'],
        ], 80000);
        self::assertSame(150000, $result['subscribed_cents']);
        self::assertSame(60000, $result['unpaid_cents']);
        self::assertSame(90000, $result['realized_cents']);
        self::assertSame(10000, $result['unexplained_cents']);
        self::assertFalse($result['in_agreement']);
    }

    public function testAReconciledFinancialBalanceDoesNotInferAnyCashBalance(): void
    {
        $result = CapitalAccountingPosition::calculate($this->statement(), [
            'subscribed' => ['A', 'B'], 'unpaid' => ['C'],
        ], 90000);
        self::assertTrue($result['in_agreement']);
        self::assertArrayNotHasKey('cash_available_cents', $result);
    }

    public function testOverlappingAccountMappingsWouldDoubleCountAndAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CapitalAccountingPosition::calculate($this->statement(), [
            'subscribed' => ['A', 'B'], 'unpaid' => ['B', 'C'],
        ], 90000);
    }

    public function testUnapprovedDeliberationNeverBecomesConfirmedCapital(): void
    {
        $assessed = CapitalAssemblyEvidence::assess([$this->allocation()], 22);
        self::assertFalse($assessed[0]['eligible']);
        self::assertSame('requires_accountant_review', $assessed[0]['reason']);
    }

    public function testApprovedAllocationsStillRequireAnAccountingEntry(): void
    {
        $event = $this->allocation();
        $event['review_state'] = 'approved';
        $assessed = CapitalAssemblyEvidence::assess([$event], 22);
        self::assertFalse($assessed[0]['eligible']);
        self::assertSame('missing_accounting_entry', $assessed[0]['reason']);
        $event['accounting_entry_ref'] = 'journal-42';
        self::assertTrue(CapitalAssemblyEvidence::assess([$event], 22)[0]['eligible']);
    }

    public function testLaterDocumentRevisionCannotDuplicateAnExistingAllocation(): void
    {
        $first = $this->allocation();
        $first['review_state'] = 'approved';
        $first['accounting_entry_ref'] = 'journal-42';
        $second = $first;
        $second['source_revision'] = 'v2';
        $second['source_sha256'] = str_repeat('f', 64);
        $assessed = CapitalAssemblyEvidence::assess([$first, $second], 22);
        self::assertTrue($assessed[0]['eligible']);
        self::assertFalse($assessed[1]['eligible']);
        self::assertSame('duplicate_source', $assessed[1]['reason']);
    }

    public function testReversalMustReferenceAnAlreadyRecognizedSource(): void
    {
        $event = $this->allocation();
        $event['kind'] = 'reversal';
        $event['amount_cents'] = -2500;
        $event['allocation_ref'] = 'reversal-1';
        $event['review_state'] = 'approved';
        $event['accounting_entry_ref'] = 'journal-43';
        $event['reverses_source_key'] = '22:AGO-2025:allocation-1:101';
        self::assertSame('unverified_reversal', CapitalAssemblyEvidence::assess([$event], 22)[0]['reason']);
        self::assertTrue(CapitalAssemblyEvidence::assess(
            [$event], 22, ['22:AGO-2025:allocation-1:101']
        )[0]['eligible']);
    }

    public function testCrossCompanyAllocationIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CapitalAssemblyEvidence::assess([$this->allocation()], 23);
    }
}
