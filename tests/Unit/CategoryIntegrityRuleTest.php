<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\Audit\{Catalog, CategoryIntegrityRule, Engine, Occurrence, Snapshot};
use PHPUnit\Framework\TestCase;

final class CategoryIntegrityRuleTest extends TestCase
{
    private static function inspectCategory(string $type, string $status, ?array $category): array
    {
        $rows = $category === null ? [] : [[
            'company_id' => 2, 'id' => 7, 'type' => $category['type'],
        ]];
        return Engine::evaluate(new Snapshot(2, 'BRL', [
            'documents' => ['available' => true, 'rows' => [[
                'company_id' => 2, 'id' => 18, 'type' => $type,
                'status' => $status, 'category_id' => 7, 'competence' => '2026-01',
            ]]],
            'categories' => ['available' => true, 'rows' => $rows],
        ]), new Catalog([new CategoryIntegrityRule()]));
    }

    public function testOppositeCategoryIsAReviewSignalForBothDocumentDirections(): void
    {
        $invoice = self::inspectCategory('invoice', 'sent', ['type' => 'expense']);
        $bill = self::inspectCategory('bill', 'paid', ['type' => 'income']);
        self::assertSame('category_opposite_direction', $invoice[0]->caseKey);
        self::assertSame(Occurrence::SUSPECTED, $invoice[0]->classification);
        self::assertSame('category_opposite_direction', $bill[0]->caseKey);
    }

    public function testLegitimateAndExtensionCategoriesAreNotFlagged(): void
    {
        self::assertSame([], self::inspectCategory('invoice', 'sent', ['type' => 'income']));
        self::assertSame([], self::inspectCategory('bill', 'paid', ['type' => 'expense']));
        self::assertSame([], self::inspectCategory('bill', 'paid', ['type' => 'direct_cost']));
        self::assertSame([], self::inspectCategory('invoice', 'sent', ['type' => 'custom_type']));
    }

    public function testMissingCategoryIsInsufficientEvidenceNotAnError(): void
    {
        $results = self::inspectCategory('invoice', 'paid', null);
        self::assertCount(1, $results);
        self::assertSame(Occurrence::INSUFFICIENT, $results[0]->classification);
        self::assertSame('info', $results[0]->severity);
    }

    public function testDraftAndCancelledDocsAreExcluded(): void
    {
        self::assertSame([], self::inspectCategory('invoice', 'draft', ['type' => 'expense']));
        self::assertSame([], self::inspectCategory('bill', 'cancelled', ['type' => 'income']));
    }

    public function testCrossCompanyCategoriesAreRejectedBySnapshot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Snapshot(2, 'BRL', [
            'documents' => ['available' => true, 'rows' => []],
            'categories' => ['available' => true, 'rows' => [
                ['company_id' => 3, 'id' => 7, 'type' => 'income']
            ]],
        ]);
    }
}
