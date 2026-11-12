<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\Audit\{Catalog, Engine, FrraIntegrityRule, Occurrence, Snapshot};
use PHPUnit\Framework\TestCase;

final class FrraIntegrityRuleTest extends TestCase
{
    private static function entry(int $document, string $source, int $cents, int $member = 5): array
    {
        return [
            'company_id' => 3, 'document_id' => $document, 'item_id' => $document * 10,
            'contact_id' => $member, 'competence' => '2026-04',
            'source' => $source, 'amount_cents' => $cents,
        ];
    }

    private static function examine(array $entries): array
    {
        return Engine::evaluate(new Snapshot(3, 'BRL', [
            'frra_entries' => ['available' => true, 'rows' => $entries]
        ]), new Catalog([new FrraIntegrityRule()]));
    }

    public function testEqualSourcesAreNotMistakenForTwoDeposits(): void
    {
        self::assertSame([], self::examine([
            self::entry(4, 'production', 1500), self::entry(8, 'annual', 1500),
        ]));
    }

    public function testDifferentSourcesAreOnlySuspected(): void
    {
        $findings = self::examine([
            self::entry(4, 'production', 1200),
            self::entry(5, 'production', 300),
            self::entry(8, 'annual', 1400),
        ]);
        self::assertCount(1, $findings);
        self::assertSame(Occurrence::SUSPECTED, $findings[0]->classification);
        self::assertSame('frra_overlap_mismatch', $findings[0]->caseKey);
        self::assertSame('8', $findings[0]->sourceId);
        self::assertSame(1500, $findings[0]->evidence['production_cents']);
        self::assertSame(1400, $findings[0]->evidence['annual_cents']);
    }

    public function testMultipleAnnualDocumentsNeedReviewNotAutomaticCorrection(): void
    {
        $findings = self::examine([
            self::entry(8, 'annual', 900), self::entry(9, 'annual', 600),
        ]);
        self::assertSame('frra_duplicate_annual', $findings[0]->caseKey);
        self::assertSame(Occurrence::SUSPECTED, $findings[0]->classification);
        self::assertSame('8', $findings[0]->sourceId);
        self::assertSame([], self::examine([self::entry(8, 'annual', 900)]));
    }

    public function testDifferentAccountingContactsRemainSeparate(): void
    {
        self::assertSame([], self::examine([
            self::entry(8, 'annual', 900, 5), self::entry(9, 'annual', 600, 6),
        ]));
    }

    public function testMissingMappingMeansInsufficientEvidence(): void
    {
        $result = Engine::evaluate(new Snapshot(3, 'BRL', [
            'frra_entries' => ['available' => false, 'rows' => []]
        ]), new Catalog([new FrraIntegrityRule()]));
        self::assertSame(Occurrence::INSUFFICIENT, $result[0]->classification);
        self::assertSame('source_unavailable', $result[0]->caseKey);
    }

    public function testCrossCompanySourceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::examine([array_replace(self::entry(8, 'annual', 100), ['company_id' => 4])]);
    }
}
