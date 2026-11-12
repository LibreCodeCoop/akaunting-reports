<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

use InvalidArgumentException;

final readonly class CapitalSocialPolicy
{
    public function __construct(
        public int $companyId,
        public int $categoryId,
        public int $itemId,
        public string $currencyCode,
        public string $refundCategoryCode = 'capital-social-refund',
        public ?int $advanceCategoryId = null,
        public ?int $advanceItemId = null,
        public ?int $legacyRefundCategoryId = null,
        public ?int $lateDeductionItemId = null,
        public ?int $excludedExpenseCategoryId = null,
    ) {
        if ($companyId <= 0 || $categoryId <= 0 || $itemId <= 0) {
            throw new InvalidArgumentException('Company, category and item identifiers must be positive.');
        }
        if (!preg_match('/^[A-Z]{3}$/D', $currencyCode)) {
            throw new InvalidArgumentException('Invalid company currency code.');
        }
        if (!preg_match('/^[a-z][a-z0-9-]{0,62}$/D', $refundCategoryCode)) {
            throw new InvalidArgumentException('Invalid refund category code.');
        }
        foreach ([$advanceCategoryId, $advanceItemId, $legacyRefundCategoryId, $lateDeductionItemId, $excludedExpenseCategoryId] as $id) {
            if ($id !== null && $id <= 0) {
                throw new InvalidArgumentException('Optional item and category identifiers must be positive.');
            }
        }
    }
}
