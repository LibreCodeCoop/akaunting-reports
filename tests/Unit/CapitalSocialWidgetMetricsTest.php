<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\CapitalSocialPolicyResolver;
use Modules\AkauntingReports\Support\CapitalSocialWidgetMetrics;
use PHPUnit\Framework\TestCase;

final class CapitalSocialWidgetMetricsTest extends TestCase
{
    public function testConfirmedBalanceExcludesPendingAmountsAndIncludesRefunds(): void
    {
        $rows = [
            ['identity' => 'contact:7', 'contact_id' => 7, 'name' => 'Member One',
                'kind' => 'integralizacao', 'amount_cents' => 10000, 'status' => 'confirmado', 'reason' => ''],
            ['identity' => 'contact:7', 'contact_id' => 7, 'name' => 'Member One',
                'kind' => 'devolucao', 'amount_cents' => -2500, 'status' => 'confirmado', 'reason' => ''],
            ['identity' => 'contact:8', 'contact_id' => 8, 'name' => 'Member Two',
                'kind' => 'retencao', 'amount_cents' => 6250, 'status' => 'revisar', 'reason' => 'Missing evidence'],
        ];

        self::assertSame([
            'total_cents' => 7500,
            'pending_count' => 1,
            'warnings_count' => 0,
            'members_count' => 2,
        ], CapitalSocialWidgetMetrics::calculate($rows));
    }

    public function testUnconfiguredCompanyDoesNotInventZeroBalance(): void
    {
        self::assertNull(CapitalSocialPolicyResolver::resolve(1, 'BRL', []));
    }

    public function testCompanySettingsResolveIndependentPolicies(): void
    {
        $first = CapitalSocialPolicyResolver::resolve(1, 'BRL', [
            'akaunting-reports.capital.category_id' => '25',
            'akaunting-reports.capital.item_id' => '11',
        ]);
        $second = CapitalSocialPolicyResolver::resolve(2, 'BRL', [
            'akaunting-reports.capital.category_id' => '35',
            'akaunting-reports.capital.item_id' => '12',
            'akaunting-reports.capital.late_deduction_item_id' => '99',
        ]);
        self::assertSame(1, $first->companyId);
        self::assertSame(25, $first->categoryId);
        self::assertSame(11, $first->itemId);
        self::assertSame(2, $second->companyId);
        self::assertSame(99, $second->lateDeductionItemId);
    }
}
