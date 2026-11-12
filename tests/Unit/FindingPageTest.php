<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use InvalidArgumentException;
use Modules\AkauntingReports\Support\Audit\{FindingPage, Occurrence};
use PHPUnit\Framework\TestCase;

final class FindingPageTest extends TestCase
{
    private static function item(int $id, string $severity = 'warning', string $month = '2026-11'): Occurrence
    {
        return new Occurrence(1, 'document_settlement', 1, Occurrence::SUSPECTED, $severity,
            'document', (string) $id, 'paid_shortfall', $month,
            ['amount_cents' => $id], 'audit.paid_shortfall', 'audit.review_source');
    }

    public function testStablePaginationDoesNotRepeatOrOmitRecords(): void
    {
        $items = [self::item(8), self::item(2), self::item(3, 'critical'),
            self::item(6), self::item(7), self::item(1), self::item(5), self::item(4)];
        $first = FindingPage::select($items, [], 1, 3);
        $second = FindingPage::select(array_reverse($items), [], 2, 3);
        $third = FindingPage::select($items, [], 3, 3);
        self::assertSame(8, $first['total']);
        self::assertSame(3, $first['pages']);
        self::assertSame('3', $first['rows'][0]->sourceId);

        $ids = array_merge(
            array_map(static fn ($a) => $a->id(), $first['rows']),
            array_map(static fn ($a) => $a->id(), $second['rows']),
            array_map(static fn ($a) => $a->id(), $third['rows']),
        );
        self::assertCount(8, array_unique($ids));
        self::assertSame(
            array_map(static fn ($a) => $a->id(), $first['all']),
            array_map(static fn ($a) => $a->id(), FindingPage::select(array_reverse($items), [])['all'])
        );
    }

    public function testFiltersAndEmptyPeriodAreExplicit(): void
    {
        $items = [self::item(1, 'warning', '2026-11'), self::item(2, 'info', '2025-02')];
        self::assertCount(1, FindingPage::select($items, ['severity' => 'info', 'year' => '2025'])['all']);
        $none = FindingPage::select($items, ['year' => '2024']);
        self::assertSame([], $none['rows']);
        self::assertSame(0, $none['total']);
    }

    public function testOutOfRangePageIsEmptyNotSilentlyReassigned(): void
    {
        $result = FindingPage::select([self::item(1)], [], 8);
        self::assertSame(8, $result['page']);
        self::assertSame([], $result['rows']);
        self::assertSame(1, $result['pages']);
    }

    public function testRejectsInvalidPageAndPageSize(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FindingPage::select([], [], 0);
    }

    public function testColumnSortingIsAppliedBeforePagination(): void
    {
        $items = [
            self::item(10, 'warning', '2026-11'),
            self::item(2, 'warning', '2026-10'),
            self::item(3, 'critical', '2026-09'),
        ];
        $first = FindingPage::select($items, [], 1, 2, 'source', 'asc');
        $second = FindingPage::select(array_reverse($items), [], 2, 2, 'source', 'asc');
        self::assertSame(['2', '3'], array_map(static fn ($item) => $item->sourceId, $first['rows']));
        self::assertSame(['10'], array_map(static fn ($item) => $item->sourceId, $second['rows']));

        $recent = FindingPage::select($items, [], 1, 3, 'competence', 'desc');
        self::assertSame(['10', '2', '3'],
            array_map(static fn ($item) => $item->sourceId, $recent['rows']));
        $critical = FindingPage::select($items, [], 1, 3);
        self::assertSame('3', $critical['rows'][0]->sourceId);
    }

    public function testAllColumnsAndDirectionsHaveStableOrder(): void
    {
        $items = [self::item(3, 'info', '2025-01'), self::item(1, 'warning', '2026-01'),
            self::item(2, 'critical', '2026-11')];
        foreach (FindingPage::SORT_COLUMNS as $column) {
            foreach (['asc', 'desc'] as $direction) {
                $first = FindingPage::select($items, [], 1, 50, $column, $direction);
                $again = FindingPage::select(array_reverse($items), [], 1, 50, $column, $direction);
                self::assertSame(
                    array_map(static fn ($item) => $item->id(), $first['all']),
                    array_map(static fn ($item) => $item->id(), $again['all']),
                    $column . ':' . $direction
                );
            }
        }
    }

    public function testRejectsUnrecognizedSortColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FindingPage::select([], [], 1, 50, 'company_id', 'asc');
    }

    public function testRejectsInvalidSortDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FindingPage::select([], [], 1, 50, 'severity', 'down');
    }
}
