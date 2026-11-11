<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once __DIR__ . '/../Support/Money.php';
require_once __DIR__ . '/../Support/CapitalSocialPolicy.php';
require_once __DIR__ . '/../Support/CapitalSocialRules.php';
require_once __DIR__ . '/../Support/CapitalSocialSummary.php';
require_once __DIR__ . '/../Support/CapitalSocialSorter.php';
require_once __DIR__ . '/../Support/CapitalSocialReview.php';

use Modules\AkauntingReports\Support\Money;
use Modules\AkauntingReports\Support\CapitalSocialPolicy;
use Modules\AkauntingReports\Support\CapitalSocialRules;
use Modules\AkauntingReports\Support\CapitalSocialSummary;
use Modules\AkauntingReports\Support\CapitalSocialSorter;
use Modules\AkauntingReports\Support\CapitalSocialReview;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$policy = new CapitalSocialPolicy(companyId: 1, categoryId: 4, itemId: 8);
$entry = ['type' => 'income', 'amount' => '123.45', 'description' => 'Quota parte', 'category_id' => 4, 'category_code' => '', 'currency_code' => 'BRL'];
$tests = 0;

check(Money::cents('2.30') === 230, 'Money conversion'); ++$tests;
check(Money::cents('-0.01') === -1, 'Negative amount'); ++$tests;
try { Money::cents('1.234'); throw new RuntimeException('Precision test did not reject'); } catch (UnexpectedValueException) { ++$tests; }
check(CapitalSocialRules::transaction($entry, $policy)['amount_cents'] === 12345, 'Direct bank integralization'); ++$tests;
check(CapitalSocialRules::transaction([...$entry,'description'=>'Devolucao de capital social'], $policy)['amount_cents'] === 0, 'Refund not counted as deposit'); ++$tests;
check(CapitalSocialRules::transaction([...$entry,'type'=>'expense','category_code'=>'capital-social-refund'], $policy)['amount_cents'] === -12345, 'Dedicated refund'); ++$tests;
check(CapitalSocialRules::transaction([...$entry,'currency_code'=>'USD'], $policy)['kind'] === 'revisao', 'No unverified FX rate'); ++$tests;
check(CapitalSocialRules::transaction([...$entry,'category_id'=>999], $policy)['kind'] === 'revisao', 'Unclassified entry'); ++$tests;
$base = ['type' => 'invoice', 'currency_code' => 'BRL', 'document_amount_cents' => 3000, 'paid_cents' => 3000, 'capital_cents' => 3000, 'capital_lines' => 1, 'item_lines' => 1, 'has_payment' => true];
check(CapitalSocialRules::document($base)['amount_cents'] === 3000, 'Paid capital invoice'); ++$tests;
check(CapitalSocialRules::document([...$base,'paid_cents'=>1000])['kind'] === 'revisao', 'Partial payment must not count'); ++$tests;
check(CapitalSocialRules::document([...$base,'has_payment'=>false])['kind'] === 'revisao', 'Unpaid capital invoice'); ++$tests;
check(CapitalSocialRules::document([...$base,'paid_cents'=>3002])['kind'] === 'revisao', 'Unexplained excess payment'); ++$tests;
check(CapitalSocialRules::document([...$base,'status'=>'cancelled'])['kind'] === 'revisao', 'Cancelled document'); ++$tests;
check(CapitalSocialRules::document([...$base,'type'=>'bill','capital_cents'=>-500])['amount_cents'] === 500, 'Production retention'); ++$tests;
check(CapitalSocialRules::document([...$base,'type'=>'bill','capital_cents'=>500])['amount_cents'] === -500, 'Capital refund bill'); ++$tests;
check(CapitalSocialRules::document([...$base,'type'=>'bill','capital_cents'=>500,'item_lines'=>2])['kind'] === 'revisao', 'Mixed refund bill'); ++$tests;
check(CapitalSocialRules::document([...$base,'type'=>'bill','capital_cents'=>-500,'document_amount_cents'=>0,'paid_cents'=>0])['amount_cents'] === 500, 'Zero-net settlement with evidence'); ++$tests;
$rows = [
    ['identity'=>'contact:1','name'=>'Member A','contact_id'=>1,'amount_cents'=>3000,'status'=>'confirmado','reason'=>''],
    ['identity'=>'contact:1','name'=>'Member A','contact_id'=>1,'amount_cents'=>-500,'status'=>'confirmado','reason'=>''],
    ['identity'=>'contact:1','name'=>'Member A','contact_id'=>1,'amount_cents'=>0,'status'=>'revisar','reason'=>'Unpaid'],
    ['identity'=>'contact:2','name'=>'Member B','contact_id'=>2,'amount_cents'=>500,'status'=>'confirmado','reason'=>''],
];
$summary = CapitalSocialSummary::calculate($rows);
check($summary['total_cents'] === 3000, 'Aggregate checked balances'); ++$tests;
check($summary['pending'] === 1, 'Ambiguous entries excluded and flagged'); ++$tests;
check(count($summary['members']) === 2, 'Independent members'); ++$tests;
$legacyPolicy = new CapitalSocialPolicy(companyId: 1, categoryId: 4, itemId: 8, legacyRefundCategoryId: 16);
$legacyRefund = [...$entry, 'type' => 'expense', 'category_id' => 16, 'description' => 'Devolucao de capital social'];
check(CapitalSocialRules::transaction($legacyRefund, $legacyPolicy)['amount_cents'] === -12345, 'Classified legacy refund'); ++$tests;
check(CapitalSocialRules::transaction([...$legacyRefund, 'description' => 'Unrelated payment'], $legacyPolicy)['kind'] === 'revisao', 'Other expenses cannot become refunds'); ++$tests;
check(CapitalSocialRules::transaction($legacyRefund, $policy)['kind'] === 'revisao', 'Legacy refund requires opt-in'); ++$tests;
$late = [...$base, 'type' => 'bill', 'status' => 'paid', 'capital_cents' => -500,
    'paid_cents' => 4000, 'post_payment_deduction_cents' => 1000];
$lateResult = CapitalSocialRules::document($late);
check($lateResult['kind'] === 'retencao' && $lateResult['amount_cents'] === 500, 'Documented late deduction retains confirmed capital'); ++$tests;
check(($lateResult['confirmed_with_warning'] ?? false) && $lateResult['reason'] !== '', 'Late deduction remains auditable'); ++$tests;
check(CapitalSocialRules::document([...$late, 'post_payment_deduction_cents' => 900])['kind'] === 'revisao', 'Mismatched late deduction remains pending'); ++$tests;
check(CapitalSocialRules::document([...$late, 'post_payment_deduction_cents' => 0])['kind'] === 'revisao', 'Excess payment without evidence remains pending'); ++$tests;
check(CapitalSocialRules::document([...$late, 'type' => 'invoice'])['kind'] === 'revisao', 'Late deduction exception is bill-only'); ++$tests;
check(CapitalSocialRules::document([...$late, 'status' => 'draft'])['kind'] === 'revisao', 'Unpaid bill cannot use exception'); ++$tests;
try {
    new CapitalSocialPolicy(companyId: 1, categoryId: 4, itemId: 8, legacyRefundCategoryId: -1);
    throw new RuntimeException('Negative legacy refund category was accepted');
} catch (InvalidArgumentException) { ++$tests; }
check(CapitalSocialRules::transaction([...$entry, 'document_id' => 61], $policy)['kind'] === 'revisao', 'Linked unrelated document must be reviewed'); ++$tests;
check(CapitalSocialRules::transaction([...$entry, 'document_id' => null], $policy)['amount_cents'] === 12345, 'Unlinked capital transaction remains confirmed'); ++$tests;
try {
    new CapitalSocialPolicy(companyId: 1, categoryId: 4, itemId: 8, excludedExpenseCategoryId: -2);
    throw new RuntimeException('Invalid external-capital category was accepted');
} catch (InvalidArgumentException) { ++$tests; }

$byType = CapitalSocialSummary::calculate([
    ['identity'=>'contact:10','name'=>'Synthetic A','contact_id'=>10,
     'kind'=>'integralizacao','amount_cents'=>10000,'status'=>'confirmado','reason'=>''],
    ['identity'=>'contact:10','name'=>'Synthetic A','contact_id'=>10,
     'kind'=>'retencao','amount_cents'=>2500,'status'=>'confirmado','reason'=>''],
    ['identity'=>'contact:10','name'=>'Synthetic A','contact_id'=>10,
     'kind'=>'devolucao','amount_cents'=>-1000,'status'=>'confirmado','reason'=>''],
    ['identity'=>'contact:10','name'=>'Synthetic A','contact_id'=>10,
     'kind'=>'revisao','amount_cents'=>0,'status'=>'revisar','reason'=>'Unsettled'],
]);
check($byType['direct_cents'] === 10000, 'Direct capital must exclude production retention'); ++$tests;
check($byType['retained_cents'] === 2500, 'Production retention has its own metric'); ++$tests;
check($byType['devolvido_cents'] === 1000, 'Refund metrics exclude deposits'); ++$tests;
check($byType['total_cents'] === 11500, 'Capital balance reconciles across movement types'); ++$tests;
check($byType['pending_count'] === 1 && $byType['confirmed_count'] === 3, 'Pending does not inflate balances'); ++$tests;
check($byType['members_count'] === 1 && $byType['movement_count'] === 4, 'Dashboard counts are source-derived'); ++$tests;
$nativeView = file_get_contents(__DIR__ . '/../Resources/views/reports/capital-social/content.blade.php');
check(str_contains($nativeView, 'column="pending" title="Pendências"')
    && str_contains($nativeView, 'aria-label="{{ $member[\'pending\'] }} {{ $member[\'pending\'] === 1 ? \'pendência\' : \'pendências\' }}"'),
    'Combined issue column retains native sorting and accessible pending counts'); ++$tests;
$beforeOverviewTable = explode('<x-table aria-label="Posição do capital social por cooperado"', $nativeView, 2)[0];
check(!str_contains($nativeView, 'min-width: 1520px')
    && !str_contains($beforeOverviewTable, 'overflow-x-auto'),
    'Member overview must not require forced wide-table horizontal scrolling'); ++$tests;
check(str_contains($nativeView, 'aria-label="Visões do relatório" class="mb-6 flex flex-wrap'),
    'Report navigation must wrap instead of overflowing on narrow phones'); ++$tests;
$overviewMarkup = explode('</x-table>', explode('<x-table aria-label="Posição do capital social por cooperado"', $nativeView, 2)[1], 2)[0];
preg_match_all('/<x-table\.th[^>]*class="[^"]*lg:w-(\d+)\/12/', $overviewMarkup, $thWidths);
preg_match_all('/<x-table\.td[^>]*class="[^"]*lg:w-(\d+)\/12/', $overviewMarkup, $tdWidths);
check(count($thWidths[1])===4 && array_sum(array_map('intval',$thWidths[1]))===12,
    'Member overview uses four balanced native composite headers'); ++$tests;
check(count($tdWidths[1])===4 && array_sum(array_map('intval',$tdWidths[1]))===12,
    'Member rows use four aligned composite data cells'); ++$tests;
check(substr_count($overviewMarkup, '<x-slot name="first"')>=6
    && substr_count($overviewMarkup, '<x-slot name="second"')>=6,
    'Native table components use stacked first and second information'); ++$tests;
check(str_contains($overviewMarkup, 'flex-wrap lg:flex-nowrap')
    && str_contains($overviewMarkup, 'lg:hidden'),
    'Native table must reflow data to stacked mobile rows'); ++$tests;
check(str_contains($overviewMarkup, "CapitalSocialReview::shortLabel")
    && !str_contains($overviewMarkup,'title="Situação">Situação</x-table.th>'),
    'Pending counters and concise explanations belong in a single cell'); ++$tests;
check(str_contains($nativeView, '<details class="w-full text-xs text-gray-500">')
    && str_contains($nativeView, 'Outras ordenações')
    && str_contains($nativeView, 'column="devolvido_cents" title="Devoluções"'),
    'Small screens retain all original native sortable fields without crowding the toolbar'); ++$tests;

check(CapitalSocialReview::shortLabel('Aguardando pagamento') === 'Pagamento pendente',
    'Unpaid movement has a concise but understandable table label'); ++$tests;
check(CapitalSocialReview::shortLabel('Revisar vínculo documental') === 'Revisar vínculo',
    'Document-link problem has an abbreviated visible reason'); ++$tests;
check(CapitalSocialReview::shortLabel('Confirmada com ressalva') === 'Confirmação com ressalva',
    'Confirmed audit reservation remains distinct from pending'); ++$tests;
check(CapitalSocialReview::shortLabel('Saldo negativo') === 'Saldo negativo',
    'Unmapped audit issues retain complete meaning'); ++$tests;
check(substr_count($overviewMarkup, 'override="class" class="lg:w-') === 4
    && substr_count($overviewMarkup, 'text-left rtl:text-right') === 4
    && !str_contains($overviewMarkup, 'kind="amount"'),
    'Four native table headers use matching left-aligned cell padding'); ++$tests;
$reportSource = file_get_contents(__DIR__ . '/../Reports/CapitalSocial.php');
check(str_contains($reportSource, "parent::setViews()")
    && !str_contains($reportSource, "views['show'] ="),
    'Core Akaunting report layout is not replaced'); ++$tests;
check(str_contains($reportSource, "views['capital']") && str_contains($reportSource, 'getAsOfDate()'),
    'Native report content and as-of filter are wired'); ++$tests;
check(is_file(__DIR__ . '/../Resources/views/reports/capital-social/content.blade.php')
    && !is_file(__DIR__ . '/../Resources/views/reports/capital-social/show.blade.php'),
    'Native report is not shadowed by an independent admin page'); ++$tests;

$syntheticMembers = [
    ['name'=>'Synthetic Z', 'contact_id'=>2, 'saldo_cents'=>20000,'direct_cents'=>19900,'retained_cents'=>100,'devolvido_cents'=>0,'pending'=>0,'last_contribution_date'=>'2026-10-10'],
    ['name'=>'Synthetic A', 'contact_id'=>1, 'saldo_cents'=>11000,'direct_cents'=>11000,'retained_cents'=>0,'devolvido_cents'=>0,'pending'=>2,'last_contribution_date'=>'2026-03-01'],
    ['name'=>'Synthetic M', 'contact_id'=>3, 'saldo_cents'=>1000,'direct_cents'=>1000,'retained_cents'=>0,'devolvido_cents'=>0,'pending'=>1,'last_contribution_date'=>'2026-11-05'],
];
$ordered = CapitalSocialSorter::sort($syntheticMembers, 'overview', null, null);
check(array_column($ordered,'contact_id') === [1,3,2], 'Default overview prioritizes unresolved pending counts'); ++$tests;
check(CapitalSocialSorter::overviewDefaults(null, null) === ['pending', 'desc'],
    'Default report preference sorts by pending count desc'); ++$tests;
check(CapitalSocialSorter::overviewDefaults('last_contribution_date', 'asc') === ['last_contribution_date', 'asc'],
    'Saved alternate column and direction are preserved'); ++$tests;
check(CapitalSocialSorter::overviewDefaults('untrusted','desc') === ['pending','desc'],
    'Invalid saved column falls back to safe pending sorting'); ++$tests;
check(CapitalSocialSorter::overviewDefaults('name','bad') === ['name','desc'],
    'Invalid saved direction falls back to descending'); ++$tests;
$ordered = CapitalSocialSorter::sort($syntheticMembers, 'overview', null, null, 'last_contribution_date', 'desc');
check(array_column($ordered,'contact_id') === [3,2,1], 'Saved recency sort applies before pagination'); ++$tests;
$ordered = CapitalSocialSorter::sort($syntheticMembers, 'overview', null, null, 'name', 'asc');
check(array_column($ordered,'contact_id') === [1,3,2], 'Saved alphabetical ascending sort is honored'); ++$tests;
$ordered = CapitalSocialSorter::sort($syntheticMembers, 'overview', 'saldo_cents','desc','pending','desc');
check(array_column($ordered,'contact_id') === [2,1,3], 'Manual native sorting overrides saved preference'); ++$tests;
$ordered = CapitalSocialSorter::sort($syntheticMembers, 'overview', 'untrusted','asc','last_contribution_date','desc');
check(array_column($ordered,'contact_id') === [3,2,1], 'Untrusted query sort falls back to configured preference'); ++$tests;
$ordered = CapitalSocialSorter::sort($syntheticMembers, 'overview', 'pending', 'desc');
check(array_column($ordered,'contact_id') === [1,3,2], 'Manual pending sort is still available'); ++$tests;
$withNoContribution = [...$syntheticMembers, ['name'=>'Synthetic N','contact_id'=>4,'saldo_cents'=>0,
    'direct_cents'=>0,'retained_cents'=>0,'devolvido_cents'=>0,'pending'=>4,'last_contribution_date'=>null]];
$ordered = CapitalSocialSorter::sort($withNoContribution, 'overview', null, null);
check(array_column($ordered, 'contact_id') === [4,1,3,2], 'Default pending order counts unconfirmed records, including members without contributions'); ++$tests;
$ordered = CapitalSocialSorter::sort($syntheticMembers, 'overview', 'saldo_cents', 'desc');
check(array_column($ordered,'contact_id') === [2,1,3], 'Explicit balance sorting overrides default pending priority'); ++$tests;
$ordered = CapitalSocialSorter::sort($syntheticMembers, 'overview', 'pending', 'asc');
check(array_column($ordered,'contact_id') === [2,3,1], 'User can reverse default pending priority'); ++$tests;
$ordered = CapitalSocialSorter::sort($syntheticMembers, 'overview', 'name', 'asc');
check(array_column($ordered,'contact_id') === [1,3,2], 'Native name sort asc'); ++$tests;
$ordered = CapitalSocialSorter::sort($syntheticMembers, 'overview', 'name', 'desc');
check(array_column($ordered,'contact_id') === [2,3,1], 'Native name sort desc'); ++$tests;
$ordered = CapitalSocialSorter::sort($syntheticMembers, 'overview', 'pending', 'desc');
check(array_column($ordered,'contact_id') === [1,3,2], 'Pending count uses numeric sorting'); ++$tests;
$ordered = CapitalSocialSorter::sort($syntheticMembers, 'overview', 'unknown;DROP TABLE', 'asc');
check($ordered[0]['contact_id'] === 1, 'Untrusted sort key falls back to default pending ordering'); ++$tests;
$ledgerRows = [
    ['id'=>3,'date'=>'2026-01-03','name'=>'A','source'=>'document','kind'=>'revisao','status'=>'revisar','amount_cents'=>0,'candidate_cents'=>5000,'running_balance_cents'=>1000],
    ['id'=>1,'date'=>'2026-01-01','name'=>'B','source'=>'transaction','kind'=>'integralizacao','status'=>'confirmado','amount_cents'=>1500,'candidate_cents'=>1500,'running_balance_cents'=>1500],
    ['id'=>2,'date'=>'2026-01-02','name'=>'B','source'=>'document','kind'=>'retencao','status'=>'confirmado','amount_cents'=>2000,'candidate_cents'=>2000,'running_balance_cents'=>3500],
];
$ordered=CapitalSocialSorter::sort($ledgerRows,'ledger','date','asc');
check(array_column($ordered,'id') === [1,2,3],'Chronological native sort asc'); ++$tests;
$ordered=CapitalSocialSorter::sort($ledgerRows,'ledger','amount_cents','desc');
check(array_column($ordered,'id') === [3,2,1],'Native amount sort uses visible pending candidate amounts'); ++$tests;
$ordered=CapitalSocialSorter::sort($ledgerRows,'ledger','date','invalid');
check(array_column($ordered,'id') === [3,2,1],'Invalid direction defaults to latest first'); ++$tests;

$unpaidRow = ['status'=>'revisar','reason'=>'Unpaid document: capital is not yet confirmed.'];
$unlinkedRow = ['status'=>'revisar','reason'=>'Capital-related transaction references a non-capital document.'];
$confirmedRow = ['status'=>'confirmado','reason'=>''];
$counts=CapitalSocialReview::counts([$unpaidRow,$unpaidRow,$unlinkedRow,$confirmedRow]);
check($counts === ['confirmed'=>1,'awaiting_payment'=>2,'requires_review'=>1],
    'Awaiting payment and ambiguous records are separate dynamic classifications'); ++$tests;
check(CapitalSocialReview::label($unpaidRow)==='Aguardando pagamento', 'Unpaid candidate is not presented as manual review'); ++$tests;
check(CapitalSocialReview::category($confirmedRow)==='confirmed','Confirmed movement remains separate'); ++$tests;
check(CapitalSocialRules::document([...$base,'has_payment'=>false])['kind']==='revisao'
    && CapitalSocialRules::document($base)['kind']==='integralizacao',
    'Capital confirmation recalculates after the underlying payment is recorded'); ++$tests;
$nativeView=file_get_contents(__DIR__.'/../Resources/views/reports/capital-social/content.blade.php');
check(str_contains($nativeView,'<x-table>') || str_contains($nativeView,'<x-table '),
    'Capital report uses the actual Akaunting table component'); ++$tests;
check(substr_count($nativeView,'<x-sortablelink') >= 12,
    'Visible columns use native Akaunting sortable links'); ++$tests;

$warningOnly=CapitalSocialSummary::calculate([
    ['identity'=>'contact:100','name'=>'Synthetic warning','contact_id'=>100,
     'kind'=>'retencao','amount_cents'=>6250,'status'=>'confirmado',
     'reason'=>'A documented non-capital deduction was added after payment; review the source document.'],
]);
check($warningOnly['pending_count']===0 && $warningOnly['warnings_count']===1,
    'Confirmed audit warning is not an unconfirmed movement'); ++$tests;
check($warningOnly['members'][0]['pending']===0 && $warningOnly['members'][0]['warnings']===1,
    'Member pending count stays separate from confirmed audit warnings'); ++$tests;
check($warningOnly['total_cents']===6250,'Confirmed with warning contributes to capital'); ++$tests;
check(CapitalSocialReview::label(['status'=>'confirmado','reason'=>'Source audit'])==='Confirmada com ressalva',
    'Confirmed warning has explicit presentation'); ++$tests;

$recent = CapitalSocialSummary::calculate([
    ['identity'=>'contact:101','name'=>'Synthetic A','contact_id'=>101,
     'kind'=>'integralizacao','amount_cents'=>10000,'status'=>'confirmado',
     'date'=>'2026-05-01','reason'=>''],
    ['identity'=>'contact:101','name'=>'Synthetic A','contact_id'=>101,
     'kind'=>'retencao','amount_cents'=>6250,'status'=>'confirmado',
     'date'=>'2026-07-15','reason'=>''],
    ['identity'=>'contact:101','name'=>'Synthetic A','contact_id'=>101,
     'kind'=>'revisao','amount_cents'=>0,'status'=>'revisar',
     'date'=>'2026-11-11','reason'=>'Unpaid'],
    ['identity'=>'contact:101','name'=>'Synthetic A','contact_id'=>101,
     'kind'=>'devolucao','amount_cents'=>-1000,'status'=>'confirmado',
     'date'=>'2026-10-02','reason'=>''],
    ['identity'=>'contact:102','name'=>'Synthetic B','contact_id'=>102,
     'kind'=>'revisao','amount_cents'=>0,'status'=>'revisar',
     'date'=>'2026-11-11','reason'=>'Unpaid'],
]);
check($recent['members'][0]['last_contribution_date']==='2026-07-15',
    'Last confirmed contribution includes retention, excludes unpaid and refunds'); ++$tests;
check($recent['members'][1]['last_contribution_date']===null,
    'Unpaid candidate is not a confirmed activity marker'); ++$tests;
check($recent['total_cents']===15250,
    'Recency metrics do not alter the financial balance'); ++$tests;
$recentSorted=CapitalSocialSorter::sort($recent['members'],'overview','last_contribution_date','desc');
check($recentSorted[0]['contact_id']===101,'Native last-contribution order prioritizes recent confirmed activity'); ++$tests;
check(str_contains($reportSource, "'attributes' => []"),
    'Native report field must supply an attributes array to the edit template'); ++$tests;
check(str_contains($reportSource, "'name' => 'default_sort'")
    && str_contains($reportSource, "'name' => 'default_direction'"),
    'Native Akaunting edit form exposes a sortable column and direction preference'); ++$tests;
check(str_contains($reportSource, "model->settings->view"),
    'Editing the saved view changes the default report presentation'); ++$tests;
check(str_contains($reportSource, "'any' => 'Qualquer pendência ou ressalva'"),
    'Native member filter must include all kinds of review'); ++$tests;
check(str_contains($reportSource, "'login_status'"),
    'Login access is a separate, explicit filter from cooperative membership'); ++$tests;

$statusSummary = CapitalSocialSummary::calculate([
    ['identity'=>'contact:21','name'=>'Synthetic A','contact_id'=>21,'kind'=>'revisao',
     'amount_cents'=>0,'status'=>'revisar',
     'reason'=>'Unpaid document: capital is not yet confirmed.'],
    ['identity'=>'contact:21','name'=>'Synthetic A','contact_id'=>21,'kind'=>'revisao',
     'amount_cents'=>0,'status'=>'revisar',
     'reason'=>'Unpaid document: capital is not yet confirmed.'],
    ['identity'=>'contact:21','name'=>'Synthetic A','contact_id'=>21,'kind'=>'revisao',
     'amount_cents'=>0,'status'=>'revisar',
     'reason'=>'Capital-related transaction references a non-capital document.'],
    ['identity'=>'contact:21','name'=>'Synthetic A','contact_id'=>21,'kind'=>'retencao',
     'amount_cents'=>6250,'status'=>'confirmado',
     'reason'=>'A documented non-capital deduction was added after payment; review the source document.'],
    ['identity'=>'contact:22','name'=>'Synthetic B','contact_id'=>22,'kind'=>'integralizacao',
     'amount_cents'=>10000,'status'=>'confirmado','reason'=>''],
]);
$issues=$statusSummary['members'][0]['status_labels'] ?? [];
check(($issues['Aguardando pagamento'] ?? 0)===2, 'Repeated unpaid reasons are grouped and counted'); ++$tests;
check(($issues['Revisar vínculo documental'] ?? 0)===1, 'Detailed linked-document problem remains visible'); ++$tests;
check(($issues['Confirmada com ressalva'] ?? 0)===1, 'Confirmed warning remains visible without becoming pending'); ++$tests;
check(($statusSummary['members'][1]['status_labels'] ?? null)===[],
    'Member with no issues has a clean status state'); ++$tests;
check($statusSummary['pending_count']===3 && $statusSummary['total_cents']===16250,
    'Status aggregation preserves pending count and confirmed balance'); ++$tests;
echo "$tests business invariants passed\n";
