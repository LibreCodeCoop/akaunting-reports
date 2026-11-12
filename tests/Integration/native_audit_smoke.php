<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\AkauntingReports\Reports\AuditInconsistencies;
use Modules\AkauntingReports\Support\Audit\DocumentSettlementReader;
use Modules\AkauntingReports\Support\Audit\ReadOnlyAuditService;

// Refuse execution against any persistent or non-test instance.
if (getenv('CI') !== 'true' || getenv('AUDIT_SYNTHETIC_DATABASE') !== 'yes') {
    fwrite(STDERR, "Refusing integration test outside isolated synthetic CI.\n");
    exit(2);
}
$appDirectory = (string) getenv('AKAUNTING_APP_DIR');
if ($appDirectory === '' || !is_file($appDirectory . '/bootstrap/app.php')) {
    throw new RuntimeException('Synthetic Akaunting is unavailable.');
}
require $appDirectory . '/vendor/autoload.php';
$app = require $appDirectory . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'audit_synthetic') {
    throw new RuntimeException('The database is not the disposable audit fixture.');
}

function requireCondition(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$company1 = (int) DB::table('companies')->orderBy('id')->value('id');
requireCondition($company1 > 0, 'Synthetic first company missing.');
requireCondition(DB::table('reports')->where('company_id', $company1)
    ->where('class', AuditInconsistencies::class)->exists(), 'Native report registration missing.');

$company2 = (int) DB::table('companies')->insertGetId([
    'domain' => 'synthetic-second.invalid', 'enabled' => 1,
    'created_at' => now(), 'updated_at' => now(),
]);
$category1 = (int) DB::table('categories')->insertGetId([
    'company_id' => $company1, 'name' => 'Synthetic income',
    'type' => 'income', 'color' => 'green-500',
    'created_at' => now(), 'updated_at' => now(),
]);
$wrongCategory1 = (int) DB::table('categories')->insertGetId([
    'company_id' => $company1, 'name' => 'Synthetic expense',
    'type' => 'expense', 'color' => 'red-500',
    'created_at' => now(), 'updated_at' => now(),
]);
$category2 = (int) DB::table('categories')->insertGetId([
    'company_id' => $company2, 'name' => 'Other company income',
    'type' => 'income', 'color' => 'green-500',
    'created_at' => now(), 'updated_at' => now(),
]);
$now = now()->toDateTimeString();
$document = static fn (int $company, string $number, string $status, string $amount, int $category): int =>
    (int) DB::table('documents')->insertGetId([
        'company_id' => $company, 'type' => 'invoice', 'document_number' => $number,
        'status' => $status, 'issued_at' => $now, 'due_at' => $now,
        'amount' => $amount, 'currency_code' => 'BRL', 'currency_rate' => 1,
        'category_id' => $category, 'contact_id' => 1, 'contact_name' => 'Synthetic customer',
        'created_at' => $now, 'updated_at' => $now,
    ]);
$paid = $document($company1, 'TEST-1', 'paid', '100.00', $category1);
$draft = $document($company1, 'TEST-2', 'draft', '70.00', $category1);
$categoryMismatch = $document($company1, 'TEST-3', 'sent', '9.00', $wrongCategory1);
$otherCompany = $document($company2, 'TEST-4', 'paid', '200.00', $category2);
$account = (int) DB::table('accounts')->where('company_id', $company1)->value('id');
requireCondition($account > 0, 'Synthetic default bank account missing.');
foreach ([$paid => '100.00', $draft => '20.00'] as $id => $amount) {
    DB::table('transactions')->insert([
        'number' => 'SYNTH-' . $id,
        'company_id' => $company1, 'document_id' => $id, 'type' => 'income',
        'paid_at' => $now, 'amount' => $amount, 'currency_code' => 'BRL',
        'currency_rate' => 1, 'account_id' => $account, 'category_id' => $category1,
        'payment_method' => 'cash', 'created_at' => $now, 'updated_at' => $now,
    ]);
}
// These NFS-e receipts are synthetic and exist only in this throwaway database.
if (getenv('AUDIT_FULL_STACK') === 'yes') {
    requireCondition(class_exists(\Modules\AkauntingReports\Support\Audit\FiscalReceiptRule::class),
        'Optional fiscal rule was not composed in the fixture.');
    requireCondition(class_exists(\Modules\AkauntingReports\Support\Audit\FrraIntegrityRule::class),
        'Optional FRA rule was not composed in the fixture.');
    requireCondition(class_exists(\Modules\AkauntingReports\Support\Audit\CapitalIntegrityRule::class),
        'Optional capital rule was not composed in the fixture.');
    requireCondition(\Modules\AkauntingReports\Support\Audit\Catalog::VERSION === 2,
        'Competence-aware rule contract was not composed.');

    \Illuminate\Support\Facades\Schema::create('nfse_receipts',
        static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id');
            $table->string('status');
            $table->json('authorized_fiscal_snapshot')->nullable();
        });
    foreach ([
        [$paid, '40.00'],
        [$paid, '60.00'],
        [$categoryMismatch, '10.00'],
    ] as [$invoiceId, $gross]) {
        DB::table('nfse_receipts')->insert([
            'invoice_id' => $invoiceId,
            'status' => 'emitted',
            'authorized_fiscal_snapshot' => json_encode([
                'source' => 'authorized_xml',
                'source_sha256' => str_repeat('a', 64),
                'gross_service_value' => $gross,
            ], JSON_THROW_ON_ERROR),
        ]);
    }
}
$company1Rows = (new DocumentSettlementReader())->read($company1, 'BRL')->rows('documents');
requireCondition(!in_array($otherCompany, array_column($company1Rows, 'id'), true),
    'Cross-company document leaked into reader.');
requireCondition(count($company1Rows) >= 3, 'Synthetic documents not fetched.');

$ledger = static fn (): string => hash('sha256', json_encode([
    DB::table('documents')->orderBy('id')->get()->toArray(),
    DB::table('transactions')->orderBy('id')->get()->toArray(),
    ...(getenv('AUDIT_FULL_STACK') === 'yes' ? [
        DB::table('nfse_receipts')->orderBy('id')->get()->toArray(),
    ] : []),
], JSON_THROW_ON_ERROR));
$before = $ledger();

$admin = App\Models\Auth\User::query()->orderBy('id')->first();
requireCondition($admin !== null, 'Synthetic admin missing.');
Auth::login($admin);
App\Models\Common\Company::findOrFail($company1)->makeCurrent();
// Akaunting's default new company uses USD; the synthetic fixtures use BRL.
setting()->set('default.currency', 'BRL');
requireCondition(default_currency() === 'BRL', 'Synthetic currency setup failed.');
// The HTTP middleware normally supplies this route parameter for print views.
\Illuminate\Support\Facades\URL::defaults(['company_id' => $company1]);
$service = new ReadOnlyAuditService();
$findings = $service->inspect($company1, 'BRL');
$cases = array_map(static fn ($f): string => $f->caseKey, $findings['findings']);
requireCondition(in_array('draft_with_payment', $cases, true), 'Draft/payment mismatch missing.');
requireCondition(in_array('category_opposite_direction', $cases, true), 'Category mismatch missing.');
requireCondition(!in_array('paid_without_payment', $cases, true),
    'Other-company paid document produced a false alert.');
if (getenv('AUDIT_FULL_STACK') === 'yes') {
    $fiscalCases = array_values(array_filter($findings['findings'],
        static fn ($f): bool => $f->caseKey === 'fiscal_gross_mismatch'));
    requireCondition(count($fiscalCases) === 1,
        'Grouped NFS-e receipts produced an unexpected number of mismatches.');
    requireCondition($fiscalCases[0]->classification === 'suspected'
        && $fiscalCases[0]->evidence['invoice_id'] === $categoryMismatch
        && $fiscalCases[0]->evidence['fiscal_gross_cents'] === 1000
        && $fiscalCases[0]->evidence['invoice_total_cents'] === 900,
        'Fiscal aggregate and invoice did not reconcile in exact cents.');
    $frra = (new \Modules\AkauntingReports\Support\Audit\FrraIntegrityReader())
        ->read($company1, 'BRL');
    $capital = (new \Modules\AkauntingReports\Support\Audit\CapitalIntegrityReader())
        ->read($company1, 'BRL');
    requireCondition($frra['available'] === false && $capital['available'] === false,
        'Unconfigured cooperative sources must remain explicitly unavailable.');
    foreach ($findings['findings'] as $finding) {
        requireCondition($finding->classification !== 'confirmed',
            'No synthetic unresolved case can be labelled a confirmed accounting error.');
    }
}
$otherCompanyDenied = false;
try {
    $service->inspect($company2, 'BRL');
} catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
    $otherCompanyDenied = $exception->getStatusCode() === 403;
}
requireCondition($otherCompanyDenied, 'Active company isolation failed.');

$reportModel = App\Models\Common\Report::query()
    ->where('company_id', $company1)
    ->where('class', AuditInconsistencies::class)
    ->firstOrFail();
$report = new AuditInconsistencies($reportModel, false);
$report->setViews();
$report->setData();
$html = view('akaunting-reports::reports.audit.content', ['class' => $report])->render();
$visibleCases = array_map(static fn ($f): string => $f->caseKey, $report->visible);
$probe = [
    'total_findings' => count($report->findings),
    'visible' => count($report->visible),
    'matching' => $report->matching,
    'filters' => [
        $report->selectedClassification, $report->selectedSeverity,
        $report->selectedSource, $report->selectedRule, $report->selectedYear,
    ],
    'cases' => array_map(static fn ($f): string => $f->caseKey, $report->findings),
];
requireCondition(in_array('draft_with_payment', $visibleCases, true),
    'Report filters discarded the draft/payment case: ' . json_encode($probe));
requireCondition(in_array('category_opposite_direction', $visibleCases, true),
    'Report filters discarded the category case.');
requireCondition(str_contains($html, 'document_settlement')
    && str_contains($html, 'category_integrity'),
    'Akaunting native audit content did not render source-linked rule identifiers.');
requireCondition(trans('akaunting-reports::audit.draft_with_payment')
    !== 'akaunting-reports::audit.draft_with_payment',
    'Native audit translations were not loaded.');
$print = $report->print()->render();
requireCondition(str_contains($print, 'Financial inconsistency audit'),
    'Akaunting print layout failed to render.');
// Exercise Akaunting's DOMPDF path, not only the print HTML template.
$pdfResponse = $report->pdf();
requireCondition(str_starts_with((string) $pdfResponse->getContent(), '%PDF-'),
    'Native audit PDF export did not produce a PDF document.');

ob_start();
$report->export()->sendContent();
$csv = (string) ob_get_clean();
requireCondition(str_contains($csv, 'document_settlement')
    && str_contains($csv, 'category_integrity'),
    'Native CSV export omitted synthetic financial findings.');
// Render against an actual widget model contract, including native actions and width.
$makeWidgetModel = static function (string $class, int $id, string $name) use ($company1): App\Models\Common\Widget {
    $model = new App\Models\Common\Widget();
    $model->id = $id;
    $model->company_id = $company1;
    $model->class = $class;
    $model->name = $name;
    $model->settings = (object) ['width' => '50'];

    return $model;
};
$auditWidgetModel = $makeWidgetModel(
    \Modules\AkauntingReports\Widgets\AuditInconsistencies::class, 901, 'Synthetic audit'
);
$widget = new \Modules\AkauntingReports\Widgets\AuditInconsistencies($auditWidgetModel);
$widgetHtml = $widget->show()->render();
requireCondition(str_contains($widgetHtml, 'By reference month'),
    'Akaunting dashboard widget failed to render its period summary.');
requireCondition(str_contains($widgetHtml, 'id="widget-901" class="w-full lg:w-2/4 lg:px-6 my-8"')
    && str_contains($widgetHtml, 'show-more-actions-widget-901')
    && str_contains($widgetHtml, 'show-more-actions-edit-widget-901'),
    'Audit widget omitted native sizing or edit/delete menu.');
requireCondition(str_contains($widgetHtml, route('reports.show', $reportModel->id))
    && str_contains($widgetHtml, trans('widgets.view_report')),
    'Audit widget omitted the native link to its own company report.');

// No cross-company fallback should produce a link to this company's report.
App\Models\Common\Company::findOrFail($company2)->makeCurrent();
requireCondition($widget->getReportUrl() === '',
    'Audit widget linked to another company report.');
App\Models\Common\Company::findOrFail($company1)->makeCurrent();

// The capital widget must be removable even before policy configuration.
$capitalWidgetModel = $makeWidgetModel(
    \Modules\AkauntingReports\Widgets\CapitalSocial::class, 902, 'Synthetic capital'
);
$capitalWidget = new \Modules\AkauntingReports\Widgets\CapitalSocial($capitalWidgetModel);
$capitalWidgetHtml = view('akaunting-reports::widgets.capital-social', [
    'class' => $capitalWidget, 'configured' => false, 'metrics' => null,
    'asOf' => now()->toDateString(),
])->render();
requireCondition(str_contains($capitalWidgetHtml, 'id="widget-902" class="w-full lg:w-2/4 lg:px-6 my-8"')
    && str_contains($capitalWidgetHtml, 'show-more-actions-widget-902')
    && str_contains($capitalWidgetHtml, 'show-more-actions-edit-widget-902'),
    'Unconfigured capital widget omitted native sizing or edit/delete menu.');
requireCondition(str_contains($capitalWidgetHtml, trans('akaunting-reports::ui.capital_widget_unconfigured')),
    'Unconfigured capital widget failed to explain its state.');
$capitalConfiguredHtml = view('akaunting-reports::widgets.capital-social', [
    'class' => $capitalWidget, 'configured' => true,
    'metrics' => ['total_cents' => 12345, 'pending_count' => 2, 'warnings_count' => 1],
    'asOf' => now()->toDateString(),
])->render();
requireCondition(str_contains($capitalConfiguredHtml, trans('akaunting-reports::ui.capital_widget_equity_note'))
    && str_contains($capitalConfiguredHtml, 'show-more-actions-widget-902'),
    'Configured capital widget lost native actions or financial context.');

// Render actual native Blade content with Akaunting's stylesheet.
// All data in this isolated preview are synthetic.
$cssPath = $appDirectory . '/public/css/app.css';
$css = is_file($cssPath) ? file_get_contents($cssPath) : false;
requireCondition(is_string($css) && $css !== '', 'Native Akaunting CSS unavailable.');
$preview = static function (string $heading, string $body) use ($css): string {
    $label = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
    return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $label . '</title><style>' . $css . '</style>'
        . '<style>body{background:#f3f5f9;padding:28px;font-family:sans-serif;}'
        . '.audit-shell{max-width:1250px;margin:0 auto;background:white;'
        . 'border:1px solid #dbe2ee;border-radius:12px;padding:24px;}'
        . '.audit-label{font-size:13px;color:#64748b;border-bottom:1px solid #dbe2ee;'
        . 'padding-bottom:12px;margin-bottom:20px;}</style></head><body>'
        . '<main class="audit-shell"><div class="audit-label">'
        . $label . ' | SYNTHETIC DATA | Akaunting 3.3.0</div>'
        . $body . '</main></body></html>';
};
requireCondition(file_put_contents('/tmp/audit-report-preview.html',
    $preview('Financial audit report', $html)) !== false, 'No report preview.');
requireCondition(file_put_contents('/tmp/audit-widget-preview.html',
    $preview('Financial audit widget', $widgetHtml)) !== false, 'No widget preview.');
requireCondition(file_put_contents('/tmp/capital-widget-preview.html',
    $preview('Member capital widget', $capitalConfiguredHtml)) !== false,
    'No capital widget preview.');
requireCondition($before === $ledger(), 'Audit changed an accounting document or transaction.');

Auth::logout();
$blocked = false;
try {
    $service->inspect($company1, 'BRL');
} catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
    $blocked = $exception->getStatusCode() === 403;
}
requireCondition($blocked, 'Unauthenticated report scan was not denied.');
echo "Native Akaunting audit synthetic integration passed: tenant SQL, findings, ACL, no mutations.\n";
