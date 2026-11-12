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
$document($company1, 'TEST-3', 'sent', '9.00', $wrongCategory1);
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
$company1Rows = (new DocumentSettlementReader())->read($company1, 'BRL')->rows('documents');
requireCondition(!in_array($otherCompany, array_column($company1Rows, 'id'), true),
    'Cross-company document leaked into reader.');
requireCondition(count($company1Rows) >= 3, 'Synthetic documents not fetched.');

$ledger = static fn (): string => hash('sha256', json_encode([
    DB::table('documents')->orderBy('id')->get()->toArray(),
    DB::table('transactions')->orderBy('id')->get()->toArray(),
], JSON_THROW_ON_ERROR));
$before = $ledger();

$admin = App\Models\Auth\User::query()->orderBy('id')->first();
requireCondition($admin !== null, 'Synthetic admin missing.');
Auth::login($admin);
App\Models\Common\Company::findOrFail($company1)->makeCurrent();
$service = new ReadOnlyAuditService();
$findings = $service->inspect($company1, 'BRL');
$cases = array_map(static fn ($f): string => $f->caseKey, $findings['findings']);
requireCondition(in_array('draft_with_payment', $cases, true), 'Draft/payment mismatch missing.');
requireCondition(in_array('category_opposite_direction', $cases, true), 'Category mismatch missing.');
requireCondition(!in_array('paid_without_payment', $cases, true),
    'Other-company paid document produced a false alert.');
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
requireCondition(in_array('draft_with_payment', $visibleCases, true),
    'Report filters discarded the draft/payment case.');
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

ob_start();
$report->export()->sendContent();
$csv = (string) ob_get_clean();
requireCondition(str_contains($csv, 'document_settlement')
    && str_contains($csv, 'category_integrity'),
    'Native CSV export omitted synthetic financial findings.');
$widget = new \Modules\AkauntingReports\Widgets\AuditInconsistencies();
$widgetHtml = $widget->show()->render();
requireCondition(str_contains($widgetHtml, 'By reference month'),
    'Akaunting dashboard widget failed to render its period summary.');

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
