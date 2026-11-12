<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Widgets;

use App\Abstracts\Widget;
use App\Utilities\Reports;
use Modules\AkauntingReports\Reports\AuditInconsistencies as Report;
use Modules\AkauntingReports\Support\Audit\ReadOnlyAuditService;

/** A bounded dashboard preview of the same tenant-scoped financial audit. */
final class AuditInconsistencies extends Widget
{
    public $default_name = 'akaunting-reports::audit.widget_title';
    public $description = 'akaunting-reports::audit.widget_description';
    public $report_class = Report::class;
    public $default_settings = ['width' => '50'];

    public function show()
    {
        if ((int) company_id() <= 0 || Reports::cannotRead(Report::class)) {
            abort(403);
        }
        $preview = app(ReadOnlyAuditService::class)->inspect((int) company_id(), default_currency(), 500);
        $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];
        $periods = [];
        foreach ($preview['findings'] as $finding) {
            ++$counts[$finding->severity];
            $month = $finding->competence ?? 'unknown';
            $periods[$month] = ($periods[$month] ?? 0) + 1;
        }
        krsort($periods, SORT_STRING);
        return $this->view('akaunting-reports::widgets.audit-inconsistencies', [
            'periods' => array_slice($periods, 0, 6, true),
            'counts' => $counts,
            'scanned' => $preview['scanned'],
            'truncated' => $preview['truncated'],
        ]);
    }
}
