<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Console;

use App\Traits\Permissions;
use App\Utilities\Reports;
use App\Utilities\Widgets;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Modules\AkauntingReports\Reports\AuditInconsistencies;
use Modules\AkauntingReports\Widgets\AuditInconsistencies as AuditWidget;

/** Explicit report/permission installation. Never mutates accounting records. */
final class InstallAudit extends Command
{
    use Permissions;

    protected $signature = 'akaunting-reports:install-audit
        {--company= : Existing Akaunting company ID}
        {--dry-run : Validate registration only; no writes}';

    protected $description = '';

    public function __construct()
    {
        parent::__construct();
        $this->setDescription(trans('akaunting-reports::audit.install_description'));
    }

    public function handle(): int
    {
        $raw = $this->option('company');
        $companyId = is_string($raw) && ctype_digit($raw) ? (int) $raw : 0;
        if ($companyId <= 0 || !DB::table('companies')->where('id', $companyId)->exists()) {
            $this->error(trans('akaunting-reports::audit.invalid_company'));
            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->info(trans('akaunting-reports::audit.dry_run'));
            return self::SUCCESS;
        }

        // Use the company locale rather than the CLI process locale.
        $locale = (string) (DB::table('settings')->where('company_id', $companyId)
            ->where('key', 'default.locale')->value('value') ?: 'en-GB');
        if (!in_array($locale, ['pt-BR', 'en-GB', 'en'], true)) {
            $locale = 'en-GB';
        }
        $name = Lang::get('akaunting-reports::audit.title', [], $locale);
        $description = Lang::get('akaunting-reports::audit.description', [], $locale);

        DB::transaction(static function () use ($companyId, $name, $description): void {
            $query = DB::table('reports')->where('company_id', $companyId)
                ->where('class', AuditInconsistencies::class)->whereNull('deleted_at');
            $existing = $query->first(['id', 'name', 'description']);
            if ($existing === null) {
                DB::table('reports')->insert([
                    'company_id' => $companyId,
                    'class' => AuditInconsistencies::class,
                    'name' => $name,
                    'description' => $description,
                    'settings' => json_encode(['classification' => 'all'], JSON_THROW_ON_ERROR),
                    'created_from' => 'akaunting-reports::install',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return;
            }

            // Do not overwrite a report label customized by an operator.
            $generatedNames = [
                'Financial Inconsistency Audit', 'Financial inconsistency audit',
                'Auditoria de Inconsistências Financeiras',
                'Auditoria de inconsistências financeiras',
            ];
            $generatedDescriptions = [
                'Source-linked review findings; no accounting records are changed.',
                'Alertas vinculados aos registros de origem; nenhum lançamento é alterado.',
            ];
            $updates = [];
            if (in_array((string) $existing->name, $generatedNames, true)
                && (string) $existing->name !== $name) {
                $updates['name'] = $name;
            }
            if (in_array((string) $existing->description, $generatedDescriptions, true)
                && (string) $existing->description !== $description) {
                $updates['description'] = $description;
            }
            if ($updates !== []) {
                DB::table('reports')->where('company_id', $companyId)->where('id', $existing->id)
                    ->update([...$updates, 'updated_at' => now()]);
            }
        });

        $permissions = [
            $this->createPermission(Reports::getPermission(AuditInconsistencies::class),
                trans('akaunting-reports::audit.report_permission')),
            $this->createPermission(Widgets::getPermission(AuditWidget::class),
                trans('akaunting-reports::audit.widget_permission')),
        ];
        $this->attachPermissionsToAdminRoles($permissions);

        $this->info(trans('akaunting-reports::audit.installed'));
        return self::SUCCESS;
    }
}
