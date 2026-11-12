<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\FrraAnnualMatrix;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrraAnnualMatrixTest extends TestCase
{
    private static function retention(
        int $contactId, string $month, string $source, int $cents, string $status = 'paid'
    ): array {
        return [
            'contact_id' => $contactId, 'name' => 'Member ' . $contactId,
            'competence' => $month, 'source' => $source,
            'amount_cents' => $cents, 'status' => $status,
        ];
    }

    public function testAnnualBillOnlyComplementsAbsentProductionCompetences(): void
    {
        $entries = [
            self::retention(1, '2026-01', 'production', 1250),
            self::retention(1, '2026-01', 'production', 250),
            self::retention(1, '2026-01', 'annual', 1500, 'draft'),
            self::retention(1, '2026-02', 'annual', 2200, 'draft'),
            self::retention(2, '2026-01', 'annual', 400, 'draft'),
        ];

        $result = FrraAnnualMatrix::build($entries, 2026);

        self::assertSame(4100, $result['total_cents']);
        self::assertSame(1500, $result['production_cents']);
        self::assertSame(2600, $result['annual_cents']);
        self::assertSame(1, $result['annual_overlap_months']);
        self::assertSame(0, $result['overlap_difference_cents']);
        self::assertSame(2, $result['annual_only_months']);
        self::assertSame(2, $result['members_count']);
        self::assertSame(1900, $result['months'][1]);
        self::assertSame(2200, $result['months'][2]);
        self::assertSame(2, $result['draft_items']);
        self::assertSame(array_sum(array_column($result['members'], 'total_cents')), $result['total_cents']);
    }

    public function testConflictingAnnualValueIsFlaggedWithoutDoubleCounting(): void
    {
        $result = FrraAnnualMatrix::build([
            self::retention(8, '2025-12', 'production', 1000),
            self::retention(8, '2025-12', 'annual', 1800),
            self::retention(8, '2025-11', 'annual', 600),
            self::retention(8, '2025-11', 'annual', 400),
        ], 2025);

        self::assertSame(2000, $result['total_cents']);
        self::assertSame(800, $result['overlap_difference_cents']);
        self::assertSame(1, $result['duplicate_annual_months']);
        self::assertSame(1000, $result['months'][11]);
        self::assertSame(1000, $result['months'][12]);
    }

    /** @return array<string, array{string, ?string}> */
    public static function competenceDescriptions(): array
    {
        return [
            'explicit accounting month' => ['Referente ao ano/mês: 2026-09', '2026-09'],
            'bare month' => ['2026-09', null],
            'bare invalid month' => ['2026-13', null],
            'zero month' => ['Referente ao ano/mês: 2026-00', null],
            'non-padded month' => ['Referente ao ano/mês: 2026-9', null],
            'natural language month' => ['September 2026', null],
            'missing description' => ['', null],
        ];
    }

    #[DataProvider('competenceDescriptions')]
    public function testCompetenceRequiresAnExplicitValidAccountingMonth(
        string $description,
        ?string $expectedCompetence
    ): void {
        self::assertSame($expectedCompetence, FrraAnnualMatrix::competence($description));
    }

    public function testYearAndMemberFiltersUseOnlyAuditedEntries(): void
    {
        $entries = [
            self::retention(2, '2026-02', 'production', 500),
            self::retention(2, '2025-12', 'annual', 250),
            self::retention(3, '2026-04', 'annual', 150),
            ['contact_id' => 4, 'name' => 'Unknown vendor', 'source' => 'unrelated', 'competence' => '2024-01', 'amount_cents' => 100],
        ];
        self::assertSame([2026 => '2026', 2025 => '2025', 2023 => '2023'],
            FrraAnnualMatrix::yearOptions($entries, [2023 => ['items' => 1, 'cents' => 250]], 2026));
        self::assertSame([2 => 'Member 2', 3 => 'Member 3'],
            FrraAnnualMatrix::memberOptions($entries, 2026));
        self::assertSame([2026 => '2026'], FrraAnnualMatrix::yearOptions([], [], 2026));
    }

    public function testRepeatedNamesRemainDistinctAccountingContacts(): void
    {
        $entries = [
            ['contact_id' => 1, 'name' => 'Member', 'source' => 'production', 'competence' => '2026-01', 'amount_cents' => 125, 'status' => 'paid'],
            ['contact_id' => 2, 'name' => 'Member', 'source' => 'production', 'competence' => '2026-01', 'amount_cents' => 275, 'status' => 'paid'],
        ];
        $result = FrraAnnualMatrix::memberOptions($entries, 2026);
        self::assertCount(2, $result);
        self::assertNotSame($result[1], $result[2]);
        self::assertSame(400, FrraAnnualMatrix::build($entries, 2026)['total_cents']);
    }
}
