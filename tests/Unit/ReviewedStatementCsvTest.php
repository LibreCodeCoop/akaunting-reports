<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use InvalidArgumentException;
use Modules\AkauntingReports\Support\ReviewedStatementCsv;
use Modules\AkauntingReports\Support\StatementReconciliation;
use Modules\AkauntingReports\Support\StatementRevisionRegistry;
use PHPUnit\Framework\TestCase;

final class ReviewedStatementCsvTest extends TestCase
{
    /** @return array<string,mixed> */
    private function metadata(string $revision = 'v1'): array
    {
        return [
            'schema_version' => 1, 'company_id' => 91, 'statement_id' => 'trial-2025',
            'revision' => $revision, 'period_start' => '2025-01-01', 'period_end' => '2025-12-31',
            'currency' => 'BRL', 'source_sha256' => str_repeat('b', 64),
        ];
    }

    /** @return array<string,string> */
    private function columns(): array
    {
        return [
            'code' => 'code', 'name' => 'name', 'row_type' => 'row_type',
            'opening' => 'opening', 'debit' => 'debit', 'credit' => 'credit', 'closing' => 'closing',
        ];
    }

    public function testTwoIndependentAccountPlansNormalizeAndReconcile(): void
    {
        $first = "code,name,row_type,opening,debit,credit,closing\n"
            . "2.1,Member capital,leaf,-100.00,0.00,50.00,-150.00\n";
        $second = "Conta;Descricao;Tipo;Inicial;Debitos;Creditos;Final\n"
            . "CAP-A;Shares, approved;leaf;-60,00;0,00;40,00;-100,00\n"
            . "CAP-B;Shares two;leaf;-40,00;0,00;10,00;-50,00\n";
        $mapping = [
            'code' => 'Conta', 'name' => 'Descricao', 'row_type' => 'Tipo',
            'opening' => 'Inicial', 'debit' => 'Debitos', 'credit' => 'Creditos', 'closing' => 'Final',
        ];
        $a = ReviewedStatementCsv::parse($first, $this->metadata(), $this->columns());
        $b = ReviewedStatementCsv::parse($second, $this->metadata(), $mapping, ';', ',');
        self::assertSame(-15000, $a['accounts'][0]['closing_cents']);
        self::assertSame(-15000, array_sum(array_column($b['accounts'], 'closing_cents')));
        self::assertSame('matched', StatementReconciliation::compare($b, $a, [
            'CAP-A' => '2.1', 'CAP-B' => '2.1',
        ])['status']);
    }

    public function testNumbersAreParsedExactlyWithNoRounding(): void
    {
        self::assertSame(-5, ReviewedStatementCsv::cents('-0.05'));
        self::assertSame(1205, ReviewedStatementCsv::cents('12,05', ','));
        self::assertSame(1200, ReviewedStatementCsv::cents('12'));
        foreach (['1,234.56', '1.234', '1e3', ' 1.00', '1,00'] as $invalid) {
            try {
                ReviewedStatementCsv::cents($invalid);
                self::fail('Expected rejection for ambiguous number.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testSubtotalRowsAreRejectedInsteadOfDoubleCounted(): void
    {
        $csv = "code,name,row_type,opening,debit,credit,closing\n"
            . "3,Subtotal,subtotal,0,0,10,-10\n"
            . "3.1,Child,leaf,0,0,10,-10\n";
        $this->expectException(InvalidArgumentException::class);
        ReviewedStatementCsv::parse($csv, $this->metadata(), $this->columns());
    }

    public function testDuplicateAccountsAreRejected(): void
    {
        $csv = "code,name,row_type,opening,debit,credit,closing\n"
            . "3.1,First,leaf,0,0,10,-10\n"
            . "3.1,Second,leaf,0,0,10,-10\n";
        $this->expectException(InvalidArgumentException::class);
        ReviewedStatementCsv::parse($csv, $this->metadata(), $this->columns());
    }

    public function testMissingMappedColumnsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ReviewedStatementCsv::parse("code,name\n2.1,Capital\n", $this->metadata(), $this->columns());
    }

    public function testConflictingRevisionCannotOverwriteOriginal(): void
    {
        $csv = "code,name,row_type,opening,debit,credit,closing\n2.1,Capital,leaf,0,0,10,-10\n";
        $first = ReviewedStatementCsv::parse($csv, $this->metadata(), $this->columns());
        $existing = StatementRevisionRegistry::stage([], $first);
        self::assertSame('pending_review', $existing[0]['state']);
        self::assertSame($existing, StatementRevisionRegistry::stage($existing, $first));
        $changed = $first;
        $changed['source_sha256'] = str_repeat('c', 64);
        $this->expectException(InvalidArgumentException::class);
        StatementRevisionRegistry::stage($existing, $changed);
    }

    public function testNewRevisionIsPreservedAsPendingReview(): void
    {
        $csv = "code,name,row_type,opening,debit,credit,closing\n2.1,Capital,leaf,0,0,10,-10\n";
        $first = ReviewedStatementCsv::parse($csv, $this->metadata(), $this->columns());
        $second = ReviewedStatementCsv::parse($csv, $this->metadata('v2'), $this->columns());
        $existing = StatementRevisionRegistry::stage(StatementRevisionRegistry::stage([], $first), $second);
        self::assertCount(2, $existing);
        self::assertSame(['v1', 'v2'], array_column(array_column($existing, 'snapshot'), 'revision'));
        self::assertSame(['pending_review', 'pending_review'], array_column($existing, 'state'));
    }

    public function testCompanyIdentityMustBeExplicit(): void
    {
        $csv = "code,name,row_type,opening,debit,credit,closing\n2.1,Capital,leaf,0,0,10,-10\n";
        $snapshot = ReviewedStatementCsv::parse($csv, $this->metadata(), $this->columns());
        self::assertSame(91, $snapshot['company_id']);
        self::assertSame(str_repeat('b', 64), $snapshot['source_sha256']);
    }
}
