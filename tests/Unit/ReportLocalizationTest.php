<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportLocalizationTest extends TestCase
{
    public function testAuditHasLocalizedReportNameAndDescription(): void
    {
        $base = dirname(__DIR__, 2) . '/Resources/lang/';
        $english = require $base . 'en/audit.php';
        $british = require $base . 'en-GB/audit.php';
        $portuguese = require $base . 'pt-BR/audit.php';
        $englishReports = require $base . 'en/reports.php';
        $portugueseReports = require $base . 'pt-BR/reports.php';

        self::assertNotSame($english['title'], $portuguese['title']);
        self::assertNotSame($english['description'], $portuguese['description']);
        self::assertSame('Financial Inconsistency Audit', $englishReports['audit_inconsistencies']);
        self::assertSame('Auditoria de Inconsistências Financeiras', $portugueseReports['audit_inconsistencies']);
        foreach (['sort', 'open_source', 'invalid_sort'] as $key) {
            self::assertArrayHasKey($key, $english);
            self::assertArrayHasKey($key, $british);
            self::assertArrayHasKey($key, $portuguese);
        }
    }

    public function testAnnualReserveHasLocalizedNamesAndExportFilenames(): void
    {
        $base = dirname(__DIR__, 2) . '/Resources/lang/';
        $english = require $base . 'en/ui.php';
        $portuguese = require $base . 'pt-BR/ui.php';
        $englishReports = require $base . 'en/reports.php';
        $portugueseReports = require $base . 'pt-BR/reports.php';

        self::assertSame('Annual Member Reserve', $english['frra_annual']);
        self::assertSame('FRRA Anual', $portuguese['frra_annual']);
        self::assertSame('Fundo Remunerado de Repouso Anual', $portuguese['frra_full_name']);
        self::assertSame($english['frra_annual'], $englishReports['frra_annual']);
        self::assertSame($portuguese['frra_annual'], $portugueseReports['frra_annual']);
        self::assertSame('annual-member-reserve', $english['frra_export_filename']);
        self::assertSame('frra-anual', $portuguese['frra_export_filename']);
        foreach (['reports.php', 'ui.php', 'cli.php', 'audit.php'] as $file) {
            $translations = require $base . 'pt-BR/' . $file;
            foreach ($translations as $value) {
                if (is_string($value)) {
                    self::assertDoesNotMatchRegularExpression('/\bFRA\b/u', $value, $file);
                }
            }
        }
    }

    /** @return array<string,array{string}> */
    public static function userFacingReserveKeys(): array
    {
        return [
            'table header' => ['frra_table'],
            'sorting label' => ['sort_frra'],
            'empty state' => ['no_frra'],
            'registered amount' => ['frra_registered'],
            'print introduction' => ['frra_print_intro'],
            'print footnote' => ['frra_print_footer_1'],
            'reconciliation description' => ['frra_reconciliation'],
        ];
    }

    #[DataProvider('userFacingReserveKeys')]
    public function testReserveLabelsArePresentAndLocalized(string $key): void
    {
        $base = dirname(__DIR__, 2) . '/Resources/lang/';
        $english = require $base . 'en/ui.php';
        $portuguese = require $base . 'pt-BR/ui.php';

        self::assertArrayHasKey($key, $english);
        self::assertArrayHasKey($key, $portuguese);
        self::assertStringNotContainsString('FRRA', $english[$key]);
    }
}
