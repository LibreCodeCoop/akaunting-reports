<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\CapitalSocialSorter;
use Modules\AkauntingReports\Support\FrraAnnualSorter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FinancialSortingTest extends TestCase
{
    /** @return array<string,array{?string,?string,list<int>}> */
    public static function frraSortCases(): array
    {
        return [
            'default name ascending' => [null, null, [1, 3, 2]],
            'total descending numerically' => ['total_cents', 'desc', [1, 2, 3]],
            'invalid column falls back to name' => ['not_allowed', 'desc', [1, 3, 2]],
        ];
    }

    #[DataProvider('frraSortCases')]
    public function testFrraSortingFollowsAllowedBusinessChoices(
        ?string $column,
        ?string $direction,
        array $expectedContacts
    ): void {
        $rows = [
            ['contact_id'=>2,'name'=>'Zulu','total_cents'=>250],
            ['contact_id'=>1,'name'=>'Alpha','total_cents'=>1000],
            ['contact_id'=>3,'name'=>'Beta','total_cents'=>100],
        ];

        self::assertSame($expectedContacts,
            array_column(FrraAnnualSorter::sort($rows, $column, $direction), 'contact_id'));
    }

    public function testCapitalSocialAllowsAuthorizedAggregateSortingOnly(): void
    {
        $rows = [
            ['contact_id'=>1, 'name'=>'Alpha', 'pending'=>0, 'saldo_cents'=>2000, 'last_contribution_date'=>'2026-01-01'],
            ['contact_id'=>2, 'name'=>'Beta', 'pending'=>2, 'saldo_cents'=>100, 'last_contribution_date'=>'2026-02-01'],
        ];
        self::assertSame([2,1], array_column(
            CapitalSocialSorter::sort($rows,'overview',null,null),'contact_id'));
        self::assertSame([1,2], array_column(
            CapitalSocialSorter::sort($rows,'overview','saldo_cents','desc'),'contact_id'));
    }
}
