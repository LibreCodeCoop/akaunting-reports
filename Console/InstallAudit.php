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

        DB::transaction(static function () use ($companyId): void {
            DB::table('reports')->updateOrInsert(
                ['company_id' => $companyId, 'class' => AuditInconsistencies::class],
                [
                    'name' => trans('akaunting-reports::audit.title'),
                    'description' => trans('akaunting-reports::audit.description'),
                    'settings' => json_encode(['classification' => 'all'], JSON_THROW_ON_ERROR),
                    'created_from' => 'akaunting-reports::install',
                    'updated_at' => now(),
                ]
            );
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
