<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Reports;

use App\Abstracts\Report;
use App\Utilities\Reports;
use Illuminate\Support\Facades\Route;
use Modules\AkauntingReports\Support\Audit\Occurrence;
use Modules\AkauntingReports\Support\Audit\FindingPage;
use Modules\AkauntingReports\Support\Audit\ReadOnlyAuditService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A native, read-only Akaunting audit report. Alerts are informational until
 * reconciled against authoritative company sources; no financial writes.
 */
final class AuditInconsistencies extends Report
{
    public $default_name = 'akaunting-reports::reports.audit_inconsistencies';
    public $category = 'general.accounting';
    public $icon = 'fact_check';
    public $type = 'audit';
    public $chart = false;
    public $has_money = false;
    public $cacheable = false;

    /** @var list<Occurrence> */
    public array $findings = [];
    /** @var list<Occurrence> */
    public array $filtered = [];
    /** @var array<string,int> */
    public array $counts = [];
    public int $scanned = 0;
    public bool $truncated = false;
    public string $selectedClassification = 'all';
    public string $selectedSeverity = 'all';
    public string $selectedSource = 'all';
    public string $selectedRule = 'all';
    public string $selectedYear = 'all';
    /** @var list<Occurrence> */
    public array $visible = [];
    public int $page = 1;
    public int $pages = 1;
    public int $matching = 0;

    public function setViews(): void
    {
        parent::setViews();
        $this->views['audit'] = 'akaunting-reports::reports.audit.content';
        $this->views['print'] = 'akaunting-reports::reports.audit.print';
    }

    public function setTables(): void { $this->tables = []; }

    public function setDates(): void { /* Source records are inspected as-is. */ }

    public function getFields(): array
    {
        return [
            [
                'type' => 'select', 'name' => 'classification',
                'title' => trans('akaunting-reports::audit.classification'),
                'values' => self::classificationOptions(),
                'selected' => 'all', 'placement' => 'strip', 'attributes' => [],
            ],
            [
                'type' => 'select', 'name' => 'severity',
                'title' => trans('akaunting-reports::audit.severity'),
                'values' => self::severityOptions(),
                'selected' => 'all', 'placement' => 'strip', 'attributes' => [],
            ],
            [
                'type' => 'select', 'name' => 'source',
                'title' => trans('akaunting-reports::audit.source'),
                'values' => self::sourceOptions(),
                'selected' => 'all', 'attributes' => [],
            ],
            [
                'type' => 'select', 'name' => 'rule',
                'title' => trans('akaunting-reports::audit.rule'),
                'values' => self::ruleOptions(), 'selected' => 'all', 'attributes' => [],
            ],
            [
                'type' => 'select', 'name' => 'year',
                'title' => trans('akaunting-reports::audit.year'),
                'values' => self::yearOptions(), 'selected' => 'all', 'attributes' => [],
            ],
        ];
    }

    public function setFilters(): void
    {
        parent::setFilters();
        foreach ([
            'classification' => self::classificationOptions(),
            'severity' => self::severityOptions(),
            'source' => self::sourceOptions(),
            'rule' => self::ruleOptions(),
            'year' => self::yearOptions(),
        ] as $name => $options) {
            $this->filters[$name] = $options;
            $this->filters['keys'][$name] = $name;
            $this->filters['names'][$name] = trans('akaunting-reports::audit.' . $name);
            $this->filters['defaults'][$name] = 'all';
            $this->filters['operators'][$name] = [
                'equal' => true, 'not_equal' => false, 'multiple' => false, 'range' => false,
            ];
        }
    }

    public function setData(): void
    {
        $companyId = (int) company_id();
        if ($companyId <= 0 || Reports::cannotRead(self::class)) {
            abort(403);
        }
        $this->selectedClassification = $this->choice('classification', self::classificationOptions());
        $this->selectedSeverity = $this->choice('severity', self::severityOptions());
        $this->selectedSource = $this->choice('source', self::sourceOptions());
        $this->selectedRule = $this->choice('rule', self::ruleOptions());
        $this->selectedYear = $this->choice('year', self::yearOptions());
        $inspection = app(ReadOnlyAuditService::class)->inspect($companyId, default_currency());
        $this->findings = $inspection['findings'];
        $this->scanned = $inspection['scanned'];
        $this->truncated = $inspection['truncated'];

        $this->counts = ['total' => count($this->findings), 'confirmed' => 0,
            'suspected' => 0, 'insufficient' => 0];
        foreach ($this->findings as $finding) {
            ++$this->counts[$finding->classification];
        }
        $requested = request()->query('page', '1');
        if (!is_scalar($requested) || !preg_match('/^[1-9][0-9]{0,3}$/D', (string) $requested)) {
            abort(422, trans('akaunting-reports::audit.invalid_page'));
        }
        $page = FindingPage::select($this->findings, [
            'classification' => $this->selectedClassification,
            'severity' => $this->selectedSeverity,
            'source' => $this->selectedSource,
            'rule' => $this->selectedRule,
            'year' => $this->selectedYear,
        ], (int) $requested);
        $this->filtered = $page['all'];
        $this->visible = $page['rows'];
        $this->page = $page['page'];
        $this->pages = $page['pages'];
        $this->matching = $page['total'];
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open audit CSV output.');
            }
            fputcsv($stream, ['rule_id', 'rule_version', 'classification', 'severity',
                'source', 'source_id', 'competence', 'occurrence_id', 'evidence']);
            foreach ($this->filtered as $occurrence) {
                fputcsv($stream, [
                    $occurrence->ruleId, $occurrence->ruleVersion,
                    $occurrence->classification, $occurrence->severity,
                    $occurrence->source, $occurrence->sourceId,
                    $occurrence->competence ?? '', $occurrence->id(),
                    json_encode($occurrence->evidence, JSON_THROW_ON_ERROR),
                ]);
            }
            fclose($stream);
        }, trans('akaunting-reports::audit.export_filename') . '.csv',
        ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function sourceUrl(Occurrence $occurrence): ?string
    {
        if ($occurrence->companyId !== (int) company_id() || !ctype_digit($occurrence->sourceId)) {
            return null;
        }
        if ($occurrence->source === 'document') {
            // Permission for the report does not imply permission for invoice/bill details.
            $type = \Illuminate\Support\Facades\DB::table('documents')
                ->where('company_id', $occurrence->companyId)
                ->whereNull('deleted_at')
                ->where('id', (int) $occurrence->sourceId)->value('type');
            $route = match ($type) {
                'invoice' => 'sales.invoices.show',
                'bill' => 'purchases.bills.show',
                default => null,
            };
            $permission = $type === 'invoice' ? 'read-sales-invoices' : 'read-purchases-bills';
            if ($route !== null && user()->can($permission) && Route::has($route)) {
                return route($route, $occurrence->sourceId);
            }
        }
        return null;
    }

    private function choice(string $name, array $allowed): string
    {
        $value = $this->getSearchStringValue($name);
        if ($value === null || $value === '') {
            return 'all';
        }
        if (!is_string($value) || !isset($allowed[$value])) {
            abort(422, trans('akaunting-reports::audit.invalid_filter'));
        }
        return $value;
    }

    public static function ruleOptions(): array
    {
        return ['all' => trans('akaunting-reports::audit.all'),
            'document_settlement' => trans('akaunting-reports::audit.document_settlement'),
            'nfse_gross_comparison' => trans('akaunting-reports::audit.nfse_gross_comparison')];
    }

    public static function yearOptions(): array
    {
        $years = ['all' => trans('akaunting-reports::audit.all')];
        $current = (int) \App\Utilities\Date::today()->year;
        for ($year = $current; $year >= 2000; --$year) {
            $years[(string) $year] = (string) $year;
        }
        return $years;
    }

    public static function classificationOptions(): array
    {
        return ['all' => trans('akaunting-reports::audit.all'),
            'confirmed' => trans('akaunting-reports::audit.confirmed'),
            'suspected' => trans('akaunting-reports::audit.suspected'),
            'insufficient' => trans('akaunting-reports::audit.insufficient')];
    }

    public static function severityOptions(): array
    {
        return ['all' => trans('akaunting-reports::audit.all'),
            'critical' => trans('akaunting-reports::audit.critical'),
            'warning' => trans('akaunting-reports::audit.warning'),
            'info' => trans('akaunting-reports::audit.info')];
    }

    public static function sourceOptions(): array
    {
        return ['all' => trans('akaunting-reports::audit.all'),
            'document' => trans('akaunting-reports::audit.document'),
            'fiscal_receipt' => trans('akaunting-reports::audit.fiscal_receipt'),
            'source' => trans('akaunting-reports::audit.source_unavailable')];
    }
}
