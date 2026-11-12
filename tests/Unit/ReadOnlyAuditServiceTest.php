<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\Audit\ReadOnlyAuditService;
use Modules\AkauntingReports\Tests\Fixtures\FakeAuditRuntime as Runtime;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Isolated simulated read-only database integration with two synthetic tenants.
 * This is not a replacement for Akaunting HTTP and live SQL integration tests.
 */
final class ReadOnlyAuditServiceTest extends TestCase
{
    private static function loadFixture(): void
    {
        require_once dirname(__DIR__) . '/fixtures/FakeAuditRuntime.php';
        Runtime::reset();
        Runtime::$tables['documents'] = [
            [
                'id' => 1, 'company_id' => 1, 'type' => 'invoice',
                'status' => 'paid', 'currency_code' => 'BRL', 'amount' => '100.00',
                'issued_at' => '2026-11-01 12:00:00', 'deleted_at' => null,
            ],
            [
                'id' => 2, 'company_id' => 2, 'type' => 'invoice',
                'status' => 'paid', 'currency_code' => 'BRL', 'amount' => '50.00',
                'issued_at' => '2026-11-01 12:00:00', 'deleted_at' => null,
            ],
            [
                'id' => 3, 'company_id' => 1, 'type' => 'bill',
                'status' => 'partial', 'currency_code' => 'BRL', 'amount' => '80.00',
                'issued_at' => '2026-11-01 12:00:00', 'deleted_at' => null,
            ],
        ];
        Runtime::$tables['transactions'] = [
            ['id' => 11, 'company_id' => 1, 'document_id' => 1,
                'type' => 'income', 'amount' => '90.00', 'currency_code' => 'BRL', 'deleted_at' => null],
            ['id' => 12, 'company_id' => 2, 'document_id' => 2,
                'type' => 'income', 'amount' => '50.00', 'currency_code' => 'BRL', 'deleted_at' => null],
            ['id' => 13, 'company_id' => 1, 'document_id' => 3,
                'type' => 'expense', 'amount' => '20.00', 'currency_code' => 'BRL', 'deleted_at' => null],
        ];
        Runtime::$tables['nfse_receipts'] = [];
    }

    #[RunInSeparateProcess]
    public function testTenantReadsOnlyOwnRowsAndNeverWrites(): void
    {
        self::loadFixture();
        $before = serialize(Runtime::$tables);
        $service = new ReadOnlyAuditService();

        $one = $service->inspect(1, 'BRL');
        self::assertSame(2, $one['scanned']);
        self::assertFalse($one['truncated']);
        self::assertCount(1, array_filter($one['findings'],
            static fn ($finding): bool => $finding->source === 'document'));
        self::assertSame('paid_shortfall', $one['findings'][0]->caseKey);

        $two = $service->inspect(2, 'BRL');
        self::assertSame(1, $two['scanned']);
        self::assertSame([], $two['findings']);
        self::assertSame($before, serialize(Runtime::$tables));
        self::assertGreaterThan(0, Runtime::$queries);
    }

    #[RunInSeparateProcess]
    public function testCategoryConflictsUseOnlyAuthorizedCompanyCategories(): void
    {
        self::loadFixture();
        Runtime::$tables['documents'][0]['category_id'] = 7;
        Runtime::$tables['documents'][1]['category_id'] = 8;
        Runtime::$tables['categories'] = [
            ['company_id' => 1, 'id' => 7, 'type' => 'expense', 'deleted_at' => null],
            ['company_id' => 2, 'id' => 8, 'type' => 'income', 'deleted_at' => null],
        ];

        $one = (new ReadOnlyAuditService())->inspect(1, 'BRL');
        self::assertSame(2, $one['scanned']);
        self::assertCount(1, array_filter($one['findings'],
            static fn ($finding): bool => $finding->ruleId === 'category_integrity'));
        $two = (new ReadOnlyAuditService())->inspect(2, 'BRL');
        self::assertSame([], $two['findings']);
    }

    #[RunInSeparateProcess]
    public function testReportAuthorizationDenialStopsAllQueries(): void
    {
        self::loadFixture();
        Runtime::$denied = true;
        try {
            (new ReadOnlyAuditService())->inspect(1, 'BRL');
            self::fail('Unauthorized read must not succeed.');
        } catch (RuntimeException $exception) {
            self::assertSame(403, $exception->getCode());
        }
        self::assertSame(0, Runtime::$queries);
    }

    #[RunInSeparateProcess]
    public function testDocumentCapIsReportedAsIncomplete(): void
    {
        self::loadFixture();
        $one = (new ReadOnlyAuditService())->inspect(1, 'BRL', 1);
        self::assertSame(1, $one['scanned']);
        self::assertTrue($one['truncated']);
        self::assertSame('paid_shortfall', $one['findings'][0]->caseKey);
    }
}
