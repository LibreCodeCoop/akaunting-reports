<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\AkauntingReports\Reports\CapitalSocial;

/** Explicit per-company provisioning; never runs automatically at module boot. */
final class InstallCapitalSocial extends Command
{
    protected $signature = 'akaunting-reports:install-capital
        {--company= : Existing company ID}
        {--category= : Existing capital category ID in the company}
        {--item= : Existing capital item ID in the company}
        {--refund-code=capital-social-refund : Dedicated refund category code}
        {--advance-category= : Optional legacy advance category ID}
        {--advance-item= : Optional legacy advance item ID}
        {--legacy-refund-category= : Optional historical refund category ID}
        {--late-deduction-item= : Optional documented post-payment deduction item ID}
        {--excluded-expense-category= : Optional external investment expense category to exclude}
        {--dry-run : Validate without modifying the database}';

    protected $description = 'Register a read-only capital social report for one company';

    public function handle(): int
    {
        $companyId = $this->positive('company');
        $categoryId = $this->positive('category');
        $itemId = $this->positive('item');
        $refundCode = (string) $this->option('refund-code');
        $advanceCategoryId = $this->optionalPositive('advance-category');
        $advanceItemId = $this->optionalPositive('advance-item');
        $legacyRefundCategoryId = $this->optionalPositive('legacy-refund-category');
        $lateDeductionItemId = $this->optionalPositive('late-deduction-item');
        $excludedExpenseCategoryId = $this->optionalPositive('excluded-expense-category');
        if (($this->option('advance-category') !== null && $advanceCategoryId === null)
            || ($this->option('advance-item') !== null && $advanceItemId === null)
            || ($this->option('legacy-refund-category') !== null && $legacyRefundCategoryId === null)
            || ($this->option('late-deduction-item') !== null && $lateDeductionItemId === null)
            || ($this->option('excluded-expense-category') !== null && $excludedExpenseCategoryId === null)) {
            $this->error('Invalid optional advance identifier.');
            return self::FAILURE;
        }
        if ($companyId === null || $categoryId === null || $itemId === null
            || !preg_match('/^[a-z][a-z0-9-]{0,62}$/D', $refundCode)) {
            $this->error('Provide valid --company, --category, --item and --refund-code values.');
            return self::FAILURE;
        }
        if (!DB::table('companies')->where('id', $companyId)->exists()
            || !DB::table('categories')->where('company_id', $companyId)->where('id', $categoryId)->exists()
            || !DB::table('items')->where('company_id', $companyId)->where('id', $itemId)->exists()) {
            $this->error('Company, category or item does not exist in the specified company.');
            return self::FAILURE;
        }
        if ($advanceCategoryId !== null && !DB::table('categories')->where('company_id', $companyId)->where('id', $advanceCategoryId)->exists()) {
            $this->error('The advance category is not part of the selected company.');
            return self::FAILURE;
        }
        if ($advanceItemId !== null && !DB::table('items')->where('company_id', $companyId)->where('id', $advanceItemId)->exists()) {
            $this->error('The advance item is not part of the selected company.');
            return self::FAILURE;
        }
        if ($legacyRefundCategoryId !== null && !DB::table('categories')->where('company_id', $companyId)->where('id', $legacyRefundCategoryId)->exists()) {
            $this->error('The legacy refund category is not part of the selected company.');
            return self::FAILURE;
        }
        if ($lateDeductionItemId !== null && !DB::table('items')->where('company_id', $companyId)->where('id', $lateDeductionItemId)->exists()) {
            $this->error('The late deduction item is not part of the selected company.');
            return self::FAILURE;
        }
        if ($excludedExpenseCategoryId !== null && !DB::table('categories')->where('company_id', $companyId)->where('id', $excludedExpenseCategoryId)->exists()) {
            $this->error('The excluded expense category is not part of the selected company.');
            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->info('Validation succeeded; no database changes were made.');
            return self::SUCCESS;
        }
        DB::transaction(static function () use ($companyId, $categoryId, $itemId, $refundCode, $advanceCategoryId, $advanceItemId, $legacyRefundCategoryId, $lateDeductionItemId, $excludedExpenseCategoryId): void {
            foreach ([
                'akaunting-reports.capital.category_id' => (string) $categoryId,
                'akaunting-reports.capital.item_id' => (string) $itemId,
                'akaunting-reports.capital.refund_code' => $refundCode,
                'akaunting-reports.capital.advance_category_id' => $advanceCategoryId === null ? '' : (string) $advanceCategoryId,
                'akaunting-reports.capital.advance_item_id' => $advanceItemId === null ? '' : (string) $advanceItemId,
                'akaunting-reports.capital.legacy_refund_category_id' => $legacyRefundCategoryId === null ? '' : (string) $legacyRefundCategoryId,
                'akaunting-reports.capital.late_deduction_item_id' => $lateDeductionItemId === null ? '' : (string) $lateDeductionItemId,
                'akaunting-reports.capital.excluded_expense_category_id' => $excludedExpenseCategoryId === null ? '' : (string) $excludedExpenseCategoryId,
            ] as $key => $value) {
                DB::table('settings')->updateOrInsert(['company_id' => $companyId, 'key' => $key], ['value' => $value]);
            }
            $report = DB::table('reports')->where('company_id', $companyId)
                ->where('class', CapitalSocial::class)->whereNull('deleted_at')->first();
            if ($report === null) {
                DB::table('reports')->insert([
                    'company_id' => $companyId,
                    'class' => CapitalSocial::class,
                    'name' => 'Capital Social',
                    'description' => 'Capital social confirmado por cooperado, com extrato e pendências de conciliação.',
                    'settings' => json_encode(['period' => 'yearly'], JSON_THROW_ON_ERROR),
                    'created_from' => 'akaunting-reports::install',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
        $this->info('The report was registered for the selected company.');
        return self::SUCCESS;
    }

    private function positive(string $key): ?int
    {
        $value = $this->option($key);
        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function optionalPositive(string $key): ?int
    {
        $value = $this->option($key);
        return $value === null || $value === '' ? null : $this->positive($key);
    }
}
