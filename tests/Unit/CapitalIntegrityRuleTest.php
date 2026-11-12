<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Tests\Unit;

use Modules\AkauntingReports\Support\Audit\{Catalog, CapitalIntegrityRule, Engine, Occurrence, Snapshot};
use Modules\AkauntingReports\Support\CapitalSocialReview;
use PHPUnit\Framework\TestCase;

final class CapitalIntegrityRuleTest extends TestCase
{
    private static function check(string $case, string $source = 'document'): array
    {
        return Engine::evaluate(new Snapshot(1, 'BRL', [
            'capital_entries' => ['available' => true, 'rows' => [[
                'company_id' => 1, 'source' => $source,
                'source_id' => 8, 'case_key' => $case, 'candidate_cents' => 6250,
            ]]],
        ]), new Catalog([new CapitalIntegrityRule()]));
    }

    public function testExcessCapitalPaymentIsOnlySuspected(): void
    {
        $finding = self::check('capital_payment_excess')[0];
        self::assertSame(Occurrence::SUSPECTED, $finding->classification);
        self::assertSame(6250, $finding->evidence['candidate_cents']);
        self::assertSame('8', $finding->sourceId);
    }

    public function testCurrencyWithoutVerificationIsInsufficientEvidence(): void
    {
        self::assertSame(Occurrence::INSUFFICIENT,
            self::check('capital_unverified_currency')[0]->classification);
    }

    public function testTransactionReferenceRemainsOpaque(): void
    {
        self::assertSame('transaction', self::check('capital_document_link', 'transaction')[0]->source);
    }

    public function testOrdinaryUnpaidOrCancelledCapitalEntriesAreNotErrors(): void
    {
        self::assertSame([], self::check('waiting_payment'));
        self::assertSame([], self::check('review_cancelled'));
        self::assertSame([], self::check('review_partial'));
    }

    public function testCodeMappingReusesExistingMemberCapitalReviewContract(): void
    {
        self::assertSame('waiting_payment',
            CapitalSocialReview::reasonKey('Unpaid document: capital is not yet confirmed.'));
        self::assertSame('review_payments',
            CapitalSocialReview::reasonKey('Payments exceed the document amount.'));
        self::assertSame('review_items',
            CapitalSocialReview::reasonKey('Document contains ambiguous capital items or direction.'));
    }

    public function testMissingConfigurationDoesNotProduceAConfirmedError(): void
    {
        $items = Engine::evaluate(new Snapshot(1, 'BRL', [
            'capital_entries' => ['available' => false, 'rows' => []]
        ]), new Catalog([new CapitalIntegrityRule()]));
        self::assertSame(Occurrence::INSUFFICIENT, $items[0]->classification);
    }

    public function testCrossCompanySourceRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Engine::evaluate(new Snapshot(1, 'BRL', [
            'capital_entries' => ['available' => true, 'rows' => [[
                'company_id' => 2, 'source' => 'document', 'source_id' => 8,
                'case_key' => 'capital_payment_excess',
            ]]],
        ]), new Catalog([new CapitalIntegrityRule()]));
    }
}
