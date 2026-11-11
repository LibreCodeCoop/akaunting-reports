<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Console;

use App\Traits\Permissions;
use App\Utilities\Reports;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\AkauntingReports\Reports\FrraAnnual;

/** Explicit, idempotent per-company registration. Never alters financial documents. */
final class InstallFrra extends Command
{
    use Permissions;

    protected $signature = 'akaunting-reports:install-frra
        {--company= : Existing company ID}
        {--production-category= : Production bill category ID for the company}
        {--annual-category= : Annual FRRA bill category ID for the company}
        {--production-prefix= : Exact prefix of production document numbers}
        {--annual-prefix= : Exact prefix of annual FRRA document numbers}
        {--item-name=FRRA : Exact name of the FRRA document item}
        {--dry-run : Validate without changing any database records}';

    protected $description = 'Register the annual FRRA matrix for one company (read only)';

    public function handle(): int
    {
        $company = $this->positive('company');
        $productionCategory = $this->positive('production-category');
        $annualCategory = $this->positive('annual-category');
        $productionPrefix = (string) $this->option('production-prefix');
        $annualPrefix = (string) $this->option('annual-prefix');
        $itemName = trim((string) $this->option('item-name'));

        if ($company === null || $productionCategory === null || $annualCategory === null
            || $productionCategory === $annualCategory
            || !self::validPrefix($productionPrefix) || !self::validPrefix($annualPrefix)
            || $productionPrefix === $annualPrefix
            || $itemName === '' || mb_strlen($itemName) > 80) {
            $this->error('Use an existing company, two distinct categories, two exact document prefixes, and an item name.');
            return self::FAILURE;
        }
        if (!DB::table('companies')->where('id', $company)->exists()
            || DB::table('categories')->where('company_id', $company)
                ->whereIn('id', [$productionCategory, $annualCategory])
                ->where('type', 'expense')->count() !== 2) {
            $this->error('Both expense categories must exist in the selected company.');
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('FRRA configuration validated; no writes performed.');
            return self::SUCCESS;
        }

        DB::transaction(static function () use (
            $company, $productionCategory, $annualCategory,
            $productionPrefix, $annualPrefix, $itemName
        ): void {
            foreach ([
                'production_category_id' => (string) $productionCategory,
                'annual_category_id' => (string) $annualCategory,
                'production_prefix' => $productionPrefix,
                'annual_prefix' => $annualPrefix,
                'item_name' => $itemName,
            ] as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['company_id' => $company, 'key' => 'akaunting-reports.frra.' . $key],
                    ['value' => $value]
                );
            }

            if (!DB::table('reports')->where('company_id', $company)
                ->where('class', FrraAnnual::class)->whereNull('deleted_at')->exists()) {
                DB::table('reports')->insert([
                    'company_id' => $company,
                    'class' => FrraAnnual::class,
                    'name' => 'FRRA Anual',
                    'description' => 'Retenções de FRRA por mês e cooperado, com totais anuais e conciliação de fontes.',
                    'settings' => json_encode(['year' => (string) now()->year], JSON_THROW_ON_ERROR),
                    'created_from' => 'akaunting-reports::install',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        // Core Akaunting's standard new-report permission, admin roles only.
        $permission = $this->createPermission(
            Reports::getPermission(FrraAnnual::class),
            'Read Akaunting Reports FRRA Annual'
        );
        $this->attachPermissionsToAdminRoles([$permission]);

        $this->info('Annual FRRA registered for the selected company; no accounting movements changed.');
        return self::SUCCESS;
    }

    private function positive(string $key): ?int
    {
        $value = $this->option($key);
        return is_string($value) && ctype_digit($value) && (int) $value > 0
            ? (int) $value : null;
    }

    private static function validPrefix(string $value): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,31}$/D', $value) === 1;
    }
}
