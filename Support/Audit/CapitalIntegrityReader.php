<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use Illuminate\Support\Facades\DB;
use Modules\AkauntingReports\Support\CapitalSocialLedger;
use Modules\AkauntingReports\Support\CapitalSocialPolicy;
use Modules\AkauntingReports\Support\CapitalSocialReview;

/** Project only authorized, company-scoped capital review codes, never identities. */
final class CapitalIntegrityReader
{
    /** @return array{available:bool,rows:list<array<string,mixed>>} */
    public function read(int $companyId, string $currency): array
    {
        if ($companyId <= 0 || preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new \InvalidArgumentException('Invalid member capital audit context.');
        }
        $prefix = 'akaunting-reports.capital.';
        $keys = ['category_id', 'item_id', 'refund_code', 'advance_category_id',
            'advance_item_id', 'legacy_refund_category_id', 'late_deduction_item_id',
            'excluded_expense_category_id'];
        $values = DB::table('settings')->where('company_id', $companyId)
            ->whereIn('key', array_map(static fn (string $key): string => $prefix . $key, $keys))
            ->pluck('value', 'key');
        $categoryId = (int) ($values[$prefix . 'category_id'] ?? 0);
        $itemId = (int) ($values[$prefix . 'item_id'] ?? 0);
        if ($categoryId <= 0 || $itemId <= 0) {
            return ['available' => false, 'rows' => []];
        }
        $optionalId = static function (string $key) use ($prefix, $values): ?int {
            $id = (int) ($values[$prefix . $key] ?? 0);
            return $id > 0 ? $id : null;
        };
        $policy = new CapitalSocialPolicy(
            companyId: $companyId,
            categoryId: $categoryId,
            itemId: $itemId,
            currencyCode: $currency,
            refundCategoryCode: (string) ($values[$prefix . 'refund_code'] ?? 'capital-social-refund'),
            advanceCategoryId: $optionalId('advance_category_id'),
            advanceItemId: $optionalId('advance_item_id'),
            legacyRefundCategoryId: $optionalId('legacy_refund_category_id'),
            lateDeductionItemId: $optionalId('late_deduction_item_id'),
            excludedExpenseCategoryId: $optionalId('excluded_expense_category_id'),
        );
        $cases = [
            'review_payments' => 'capital_payment_excess',
            'review_link' => 'capital_document_link',
            'review_items' => 'capital_ambiguous_items',
            'review_currency' => 'capital_unverified_currency',
            'review_conversion' => 'capital_unverified_currency',
            'review_classification' => 'capital_ambiguous_classification',
        ];
        $rows = [];
        foreach ((new CapitalSocialLedger())->rows($policy) as $movement) {
            if (($movement['status'] ?? '') !== 'revisar') {
                continue;
            }
            $reason = CapitalSocialReview::reasonKey((string) ($movement['reason'] ?? ''));
            $case = $cases[$reason] ?? null;
            $source = (string) ($movement['source'] ?? '');
            $id = (int) ($movement['id'] ?? 0);
            if ($case === null || $id <= 0 || !in_array($source, ['document', 'transaction'], true)) {
                continue; // Unpaid, partial and cancelled records are not automatic errors.
            }
            $rows[] = [
                'company_id' => $companyId, 'source' => $source,
                'source_id' => $id, 'case_key' => $case,
                'candidate_cents' => max(0, (int) ($movement['candidate_cents'] ?? 0)),
            ];
        }
        return ['available' => true, 'rows' => $rows];
    }
}
