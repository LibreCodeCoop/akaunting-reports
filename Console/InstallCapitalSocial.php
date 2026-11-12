<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Console;

use Illuminate\Console\Command;
use App\Traits\Permissions;
use App\Utilities\Widgets;
use Modules\AkauntingReports\Widgets\CapitalSocial as CapitalSocialWidget;
use Illuminate\Support\Facades\DB;
use Modules\AkauntingReports\Reports\CapitalSocial;

/** Explicit per-company provisioning; never runs automatically at module boot. */
final class InstallCapitalSocial extends Command
{
    use Permissions;

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

    protected $description = '';

    public function __construct()
    {
        parent::__construct();
        $this->setDescription(trans('akaunting-reports::cli.capital_description'));
    }

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
            $this->error(trans('akaunting-reports::cli.invalid_advance'));
            return self::FAILURE;
        }
        if ($companyId === null || $categoryId === null || $itemId === null
            || !preg_match('/^[a-z][a-z0-9-]{0,62}$/D', $refundCode)) {
            $this->error(trans('akaunting-reports::cli.invalid_capital_options'));
            return self::FAILURE;
        }
        if (!DB::table('companies')->where('id', $companyId)->exists()
            || !DB::table('categories')->where('company_id', $companyId)->where('id', $categoryId)->exists()
            || !DB::table('items')->where('company_id', $companyId)->where('id', $itemId)->exists()) {
            $this->error(trans('akaunting-reports::cli.capital_missing'));
            return self::FAILURE;
        }
        if ($advanceCategoryId !== null && !DB::table('categories')->where('company_id', $companyId)->where('id', $advanceCategoryId)->exists()) {
            $this->error(trans('akaunting-reports::cli.advance_category_missing'));
            return self::FAILURE;
        }
        if ($advanceItemId !== null && !DB::table('items')->where('company_id', $companyId)->where('id', $advanceItemId)->exists()) {
            $this->error(trans('akaunting-reports::cli.advance_item_missing'));
            return self::FAILURE;
        }
        if ($legacyRefundCategoryId !== null && !DB::table('categories')->where('company_id', $companyId)->where('id', $legacyRefundCategoryId)->exists()) {
            $this->error(trans('akaunting-reports::cli.legacy_refund_missing'));
            return self::FAILURE;
        }
        if ($lateDeductionItemId !== null && !DB::table('items')->where('company_id', $companyId)->where('id', $lateDeductionItemId)->exists()) {
            $this->error(trans('akaunting-reports::cli.late_deduction_missing'));
            return self::FAILURE;
        }
        if ($excludedExpenseCategoryId !== null && !DB::table('categories')->where('company_id', $companyId)->where('id', $excludedExpenseCategoryId)->exists()) {
            $this->error(trans('akaunting-reports::cli.excluded_category_missing'));
            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->info(trans('akaunting-reports::cli.dry_run_success'));
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
                    'name' => trans('akaunting-reports::ui.capital_social'),
                    'description' => trans('akaunting-reports::ui.capital_reconciliation'),
                    'settings' => json_encode(['period' => 'yearly'], JSON_THROW_ON_ERROR),
                    'created_from' => 'akaunting-reports::install',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
        // This command already validates company-specific configuration; its
        // repeatable execution also provisions the dashboard widget permission.
        $widgetPermission = $this->createPermission(
            Widgets::getPermission(CapitalSocialWidget::class),
            trans('akaunting-reports::ui.capital_widget_permission')
        );
        $this->attachPermissionsToAdminRoles([$widgetPermission]);

        $this->info(trans('akaunting-reports::cli.capital_installed'));
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
