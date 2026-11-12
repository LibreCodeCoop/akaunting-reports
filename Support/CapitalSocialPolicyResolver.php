<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

use InvalidArgumentException;

/**
 * One mapping of per-company settings shared by the report and its widget.
 * The resolver never guesses missing category or item configuration.
 */
final class CapitalSocialPolicyResolver
{
    public const SETTING_KEYS = [
        'akaunting-reports.capital.category_id',
        'akaunting-reports.capital.item_id',
        'akaunting-reports.capital.refund_code',
        'akaunting-reports.capital.advance_category_id',
        'akaunting-reports.capital.advance_item_id',
        'akaunting-reports.capital.legacy_refund_category_id',
        'akaunting-reports.capital.late_deduction_item_id',
        'akaunting-reports.capital.excluded_expense_category_id',
    ];

    /** @param array<string,mixed> $settings */
    public static function resolve(int $companyId, string $currencyCode, array $settings): ?CapitalSocialPolicy
    {
        if ($companyId <= 0) {
            throw new InvalidArgumentException('An active company is required.');
        }
        $categoryId = (int) ($settings['akaunting-reports.capital.category_id'] ?? 0);
        $itemId = (int) ($settings['akaunting-reports.capital.item_id'] ?? 0);
        if ($categoryId <= 0 || $itemId <= 0) {
            return null;
        }

        return new CapitalSocialPolicy(
            companyId: $companyId,
            categoryId: $categoryId,
            itemId: $itemId,
            currencyCode: $currencyCode,
            refundCategoryCode: (string) ($settings['akaunting-reports.capital.refund_code'] ?? 'capital-social-refund'),
            advanceCategoryId: self::positiveOrNull($settings['akaunting-reports.capital.advance_category_id'] ?? null),
            advanceItemId: self::positiveOrNull($settings['akaunting-reports.capital.advance_item_id'] ?? null),
            legacyRefundCategoryId: self::positiveOrNull($settings['akaunting-reports.capital.legacy_refund_category_id'] ?? null),
            lateDeductionItemId: self::positiveOrNull($settings['akaunting-reports.capital.late_deduction_item_id'] ?? null),
            excludedExpenseCategoryId: self::positiveOrNull($settings['akaunting-reports.capital.excluded_expense_category_id'] ?? null),
        );
    }

    private static function positiveOrNull(mixed $value): ?int
    {
        return $value !== null && (int) $value > 0 ? (int) $value : null;
    }
}
