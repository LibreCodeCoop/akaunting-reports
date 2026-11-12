<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use InvalidArgumentException;
use LogicException;
use Modules\AkauntingReports\Support\Audit\{Catalog, Engine, Occurrence, Rule, Snapshot};
use PHPUnit\Framework\TestCase;

final class AuditContractTest extends TestCase
{
    private static function rule(): Rule
    {
        return new class implements Rule {
            public function id(): string { return 'demo_rule'; }
            public function version(): int { return 1; }
            public function sources(): array { return ['documents']; }
            public function inspect(Snapshot $snapshot): array
            {
                return array_map(static fn (array $row) => new Occurrence(
                    $snapshot->companyId, 'demo_rule', 1, Occurrence::SUSPECTED,
                    'warning', 'document', (string) $row['id'], 'review_amount',
                    '2026-11', ['amount_cents' => 1001, 'needs_review' => true],
                    'audit.review_amount', 'audit.review_source',
                ), $snapshot->rows('documents'));
            }
        };
    }

    private static function snapshot(int $company = 1, bool $available = true): Snapshot
    {
        return new Snapshot($company, 'BRL', ['documents' => [
            'available' => $available,
            'rows' => $available ? [['company_id' => $company, 'id' => 10]] : [],
        ]]);
    }

    public function testStableTenantSpecificFindingsAndExactCentEvidence(): void
    {
        $catalog = new Catalog([self::rule()]);
        $a = Engine::evaluate(self::snapshot(), $catalog);
        $b = Engine::evaluate(self::snapshot(), $catalog);
        self::assertSame($a[0]->toArray(), $b[0]->toArray());
        self::assertSame(1001, $a[0]->evidence['amount_cents']);
        self::assertNotSame($a[0]->id(), Engine::evaluate(self::snapshot(2), $catalog)[0]->id());
        self::assertSame('demo_rule', $catalog->manifest()[0]['id']);
    }

    public function testSameSourceInDifferentCompetencesHasDistinctStableIds(): void
    {
        $first = new Occurrence(1, 'demo_rule', 1, Occurrence::SUSPECTED,
            'warning', 'document', '10', 'review_amount', '2026-01',
            ['amount_cents' => 1001], 'audit.review_amount', 'audit.review_source');
        $second = new Occurrence(1, 'demo_rule', 1, Occurrence::SUSPECTED,
            'warning', 'document', '10', 'review_amount', '2026-02',
            ['amount_cents' => 1001], 'audit.review_amount', 'audit.review_source');
        $updated = new Occurrence(1, 'demo_rule', 1, Occurrence::SUSPECTED,
            'warning', 'document', '10', 'review_amount', '2026-01',
            ['amount_cents' => 2500], 'audit.review_amount', 'audit.review_source');
        self::assertNotSame($first->id(), $second->id());
        self::assertSame($first->id(), $updated->id());
        self::assertSame(2, Catalog::VERSION);
    }
    public function testEmptyAndUnavailableSourcesHaveDifferentMeaning(): void
    {
        $empty = new Snapshot(1, 'BRL', ['documents' => ['available' => true, 'rows' => []]]);
        self::assertSame([], Engine::evaluate($empty, new Catalog([self::rule()])));
        $missing = Engine::evaluate(self::snapshot(1, false), new Catalog([self::rule()]));
        self::assertCount(1, $missing);
        self::assertSame(Occurrence::INSUFFICIENT, $missing[0]->classification);
        self::assertSame('info', $missing[0]->severity);
    }

    public function testRejectsCrossCompanyInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Snapshot(1, 'BRL', ['documents' => ['available' => true,
            'rows' => [['company_id' => 2, 'id' => 10]]]]);
    }

    public function testRejectsInputWithoutCompanyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Snapshot(1, 'BRL', ['documents' => ['available' => true,
            'rows' => [['id' => 10]]]]);
    }

    public function testRejectsSensitiveEvidence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Occurrence(1, 'demo_rule', 1, Occurrence::SUSPECTED, 'warning',
            'document', '10', 'review_amount', null, ['contact_name' => 'Private'],
            'audit.review_amount', 'audit.review_source');
    }

    public function testRejectsDuplicateRuleDefinitions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Catalog([self::rule(), self::rule()]);
    }

    public function testRuleCannotReturnFindingFromAnotherCompany(): void
    {
        $bad = new class implements Rule {
            public function id(): string { return 'bad_rule'; }
            public function version(): int { return 1; }
            public function sources(): array { return ['documents']; }
            public function inspect(Snapshot $s): array
            {
                return [new Occurrence(2, 'bad_rule', 1, Occurrence::CONFIRMED,
                    'critical', 'document', '10', 'wrong_company', null, [],
                    'audit.wrong_company', 'audit.review_source')];
            }
        };
        $this->expectException(LogicException::class);
        Engine::evaluate(self::snapshot(), new Catalog([$bad]));
    }
}
