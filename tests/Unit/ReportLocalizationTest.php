<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReportLocalizationTest extends TestCase
{
    public function testAnnualReserveHasLocalizedNamesAndExportFilenames(): void
    {
        $base = dirname(__DIR__, 2) . '/Resources/lang/';
        $english = require $base . 'en/ui.php';
        $portuguese = require $base . 'pt-BR/ui.php';
        $englishReports = require $base . 'en/reports.php';
        $portugueseReports = require $base . 'pt-BR/reports.php';

        self::assertSame('Annual Member Reserve', $english['frra_annual']);
        self::assertSame('FRA Anual', $portuguese['frra_annual']);
        self::assertSame($english['frra_annual'], $englishReports['frra_annual']);
        self::assertSame($portuguese['frra_annual'], $portugueseReports['frra_annual']);
        self::assertSame('annual-member-reserve', $english['frra_export_filename']);
        self::assertSame('fra-anual', $portuguese['frra_export_filename']);

        foreach ([
            'frra_table', 'sort_frra', 'no_frra', 'frra_registered',
            'frra_print_intro', 'frra_print_footer_1', 'frra_reconciliation',
        ] as $key) {
            self::assertArrayHasKey($key, $english);
            self::assertArrayHasKey($key, $portuguese);
            self::assertStringNotContainsString('FRRA', $english[$key]);
        }
    }
}
