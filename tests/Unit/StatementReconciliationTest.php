<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use InvalidArgumentException;
use Modules\AkauntingReports\Support\StatementReconciliation;
use Modules\AkauntingReports\Support\StatementSnapshot;
use PHPUnit\Framework\TestCase;

final class StatementReconciliationTest extends TestCase
{
    private function snapshot(array $rows, string $revision = 'v1'): array
    {
        return [
            'schema_version' => 1, 'company_id' => 42,
            'statement_id' => 'trial-2025', 'revision' => $revision,
            'period_start' => '2025-01-01', 'period_end' => '2025-12-31',
            'currency' => 'BRL', 'source_sha256' => str_repeat('a', 64), 'accounts' => $rows,
        ];
    }

    private function account(string $code, int $opening, int $debit, int $credit, int $closing): array
    {
        return [
            'code' => $code, 'name' => "Synthetic {$code}",
            'opening_cents' => $opening, 'debit_cents' => $debit,
            'credit_cents' => $credit, 'closing_cents' => $closing,
        ];
    }

    public function testDifferentExternalPlansCanMapToOneInternalAccount(): void
    {
        $internal = $this->snapshot([$this->account('capital', -10000, 0, 5000, -15000)]);
        $a = $this->snapshot([$this->account('3.1', -10000, 0, 5000, -15000)]);
        $b = $this->snapshot([
            $this->account('2.10', -6000, 0, 4000, -10000),
            $this->account('2.11', -4000, 0, 1000, -5000),
        ]);
        self::assertSame('matched', StatementReconciliation::compare($a, $internal, ['3.1' => 'capital'])['status']);
        self::assertSame('matched', StatementReconciliation::compare($b, $internal, ['2.10' => 'capital', '2.11' => 'capital'])['status']);
    }

    public function testUnmappedAccountIsNotSilentlyIgnored(): void
    {
        $external = $this->snapshot([$this->account('3.1', -10000, 0, 5000, -15000)]);
        $internal = $this->snapshot([$this->account('capital', -10000, 0, 5000, -15000)]);
        $result = StatementReconciliation::compare($external, $internal, []);
        self::assertSame('incomplete', $result['status']);
        self::assertSame(['3.1'], $result['unmapped']);
    }

    public function testDiscrepancyIsVisibleWithoutAdjustments(): void
    {
        $external = $this->snapshot([$this->account('3.1', -10000, 0, 4000, -14000)]);
        $internal = $this->snapshot([$this->account('capital', -10000, 0, 5000, -15000)]);
        $result = StatementReconciliation::compare($external, $internal, ['3.1' => 'capital']);
        self::assertSame('divergent', $result['status']);
        self::assertSame(1000, $result['differences'][0]['delta']['closing_cents']);
    }

    public function testRevisionsAreDistinctAndNormalizationIsDeterministic(): void
    {
        $first = $this->snapshot([$this->account('B', 0, 200, 0, 200), $this->account('A', 0, 0, 100, -100)]);
        self::assertSame(
            StatementSnapshot::normalize($first),
            StatementSnapshot::normalize($this->snapshot(array_reverse($first['accounts'])))
        );
        self::assertNotSame(
            StatementSnapshot::normalize($first),
            StatementSnapshot::normalize($this->snapshot($first['accounts'], 'v2'))
        );
    }

    public function testInvalidTrialBalanceEquationFails(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StatementSnapshot::normalize($this->snapshot([$this->account('3.1', 0, 1000, 0, 900)]));
    }

    public function testCompanyIsolationIsRequired(): void
    {
        $a = $this->snapshot([$this->account('3.1', 0, 0, 100, -100)]);
        $b = $this->snapshot([$this->account('capital', 0, 0, 100, -100)]);
        $b['company_id'] = 43;
        $this->expectException(InvalidArgumentException::class);
        StatementReconciliation::compare($a, $b, ['3.1' => 'capital']);
    }
    public function testSnapshotRejectsOverflowInDebitMovement(): void
    {
        $invalid = $this->snapshot([$this->account('3.1', PHP_INT_MAX, 1, 0, PHP_INT_MAX)]);
        $this->expectException(InvalidArgumentException::class);
        StatementSnapshot::normalize($invalid);
    }

    public function testMappedAggregationRejectsOverflow(): void
    {
        $external = $this->snapshot([
            $this->account('A', PHP_INT_MAX, 0, 0, PHP_INT_MAX),
            $this->account('B', 1, 0, 0, 1),
        ]);
        $internal = $this->snapshot([$this->account('target', 0, 0, 0, 0)]);
        $this->expectException(InvalidArgumentException::class);
        StatementReconciliation::compare($external, $internal, ['A' => 'target', 'B' => 'target']);
    }

    public function testComparisonDeltaRejectsOverflow(): void
    {
        $external = $this->snapshot([$this->account('A', PHP_INT_MAX, 0, 0, PHP_INT_MAX)]);
        $internal = $this->snapshot([$this->account('target', -1, 0, 0, -1)]);
        $this->expectException(InvalidArgumentException::class);
        StatementReconciliation::compare($external, $internal, ['A' => 'target']);
    }

}
