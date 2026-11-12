<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\CapitalSocialSummary;
use PHPUnit\Framework\TestCase;

final class CapitalSocialSummaryTest extends TestCase
{
    public function testRefundsReduceBalanceButPendingRecordsDoNot(): void
    {
        $rows = [
            ['identity'=>'contact:1', 'contact_id'=>1, 'name'=>'Member A', 'kind'=>'integralizacao',
                'amount_cents'=>10000, 'status'=>'confirmado', 'reason'=>''],
            ['identity'=>'contact:1', 'contact_id'=>1, 'name'=>'Member A', 'kind'=>'retencao',
                'amount_cents'=>2500, 'status'=>'confirmado', 'reason'=>''],
            ['identity'=>'contact:1', 'contact_id'=>1, 'name'=>'Member A', 'kind'=>'devolucao',
                'amount_cents'=>-1500, 'status'=>'confirmado', 'reason'=>''],
            ['identity'=>'contact:1', 'contact_id'=>1, 'name'=>'Member A', 'kind'=>'revisao',
                'amount_cents'=>50000, 'status'=>'revisar', 'reason'=>'Ambiguous allocation'],
        ];
        $summary = CapitalSocialSummary::calculate($rows);
        self::assertSame(11000, $summary['total_cents']);
        self::assertSame(1, $summary['pending_count']);
        self::assertSame(10000, $summary['direct_cents']);
        self::assertSame(2500, $summary['retained_cents']);
        self::assertSame(1500, $summary['devolvido_cents']);
        self::assertSame(11000, $summary['members'][0]['saldo_cents']);
    }
}
