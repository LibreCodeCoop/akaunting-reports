<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Widgets;

use App\Abstracts\Widget;
use App\Utilities\Date;
use App\Utilities\Reports;
use App\Utilities\Widgets;
use Illuminate\Support\Facades\DB;
use Modules\AkauntingReports\Reports\CapitalSocial as CapitalReport;
use Modules\AkauntingReports\Support\CapitalSocialLedger;
use Modules\AkauntingReports\Support\CapitalSocialPolicyResolver;
use Modules\AkauntingReports\Support\CapitalSocialWidgetMetrics;

/** Read-only company-scoped dashboard view of confirmed member equity. */
final class CapitalSocial extends Widget
{
    public $default_name = 'akaunting-reports::ui.capital_widget_title';
    public $description = 'akaunting-reports::ui.capital_widget_description';
    public $report_class = CapitalReport::class;
    public $default_settings = ['width' => '50'];

    public function show()
    {
        $companyId = (int) company_id();
        if ($companyId <= 0 || Widgets::cannotRead(self::class)
            || Reports::cannotRead(CapitalReport::class)) {
            abort(403);
        }

        $asOf = Date::today()->toDateString();
        $settings = DB::table('settings')->where('company_id', $companyId)
            ->whereIn('key', CapitalSocialPolicyResolver::SETTING_KEYS)->pluck('value', 'key');
        $policy = CapitalSocialPolicyResolver::resolve($companyId, default_currency(), $settings->all());
        if ($policy === null) {
            return $this->view('akaunting-reports::widgets.capital-social', [
                'configured' => false, 'metrics' => null, 'asOf' => $asOf,
            ]);
        }

        $movements = app(CapitalSocialLedger::class)->rows($policy, null, $asOf);
        $metrics = CapitalSocialWidgetMetrics::calculate($movements);

        return $this->view('akaunting-reports::widgets.capital-social', [
            'configured' => true, 'metrics' => $metrics, 'asOf' => $asOf,
        ]);
    }
}
