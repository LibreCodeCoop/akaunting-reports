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
require_once __DIR__ . '/../Support/FrraAnnualMatrix.php';
require_once __DIR__ . '/../Support/FrraAnnualSorter.php';

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
check(str_contains($nativeView, 'column="pending" title="Pendências e ressalvas"')
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
$tiedPending = [
    ['contact_id'=>101, 'name'=>'Alpha older', 'pending'=>2,
        'last_contribution_date'=>'2026-02-01', 'saldo_cents'=>25000],
    ['contact_id'=>102, 'name'=>'Zulu recent', 'pending'=>2,
        'last_contribution_date'=>'2026-11-09', 'saldo_cents'=>20000],
    ['contact_id'=>103, 'name'=>'Beta older', 'pending'=>0,
        'last_contribution_date'=>'2025-12-01', 'saldo_cents'=>10000],
    ['contact_id'=>104, 'name'=>'Yankee recent', 'pending'=>0,
        'last_contribution_date'=>'2026-11-08', 'saldo_cents'=>30000],
    ['contact_id'=>105, 'name'=>'Missing activity', 'pending'=>0,
        'last_contribution_date'=>null, 'saldo_cents'=>10000],
];
$compoundDefault = CapitalSocialSorter::sort($tiedPending, 'overview', null, null);
check(array_column($compoundDefault, 'contact_id') === [102,101,104,103,105],
    'Compound default: pending groups sort by most recent confirmed contribution'); ++$tests;
check(CapitalSocialSorter::overviewSecondaryDefaults(null, null)
    === ['last_contribution_date', 'desc'], 'Default secondary preference prioritizes recency'); ++$tests;
check(CapitalSocialSorter::overviewTertiaryDefaults(null, null)
    === ['name', 'asc'], 'Default third preference is alphabetic only after recency'); ++$tests;
check(CapitalSocialSorter::overviewSecondaryDefaults('invalid','up')
    === ['last_contribution_date','desc'], 'Invalid second settings fall back to recent first'); ++$tests;
check(CapitalSocialSorter::overviewTertiaryDefaults(['name'],'down')
    === ['name','asc'], 'Malformed third settings cannot bypass the allowed list'); ++$tests;
$customTieBreakers = [
    ['column'=>'name','direction'=>'asc'],
    ['column'=>'last_contribution_date','direction'=>'desc'],
];
$customNames = CapitalSocialSorter::sort(
    $tiedPending, 'overview', null, null, 'pending', 'desc', $customTieBreakers
);
check(array_column($customNames,'contact_id') === [101,102,103,105,104],
    'Saved secondary and tertiary sort columns apply in configured sequence'); ++$tests;
$secondaryBalance = CapitalSocialSorter::sort(
    $tiedPending, 'overview', null, null, 'pending', 'desc',
    [['column'=>'saldo_cents','direction'=>'desc'], ['column'=>'name','direction'=>'asc']]
);
check(array_column($secondaryBalance,'contact_id') === [101,102,104,103,105],
    'Saved secondary balance sorting changes tie order without changing pending groups'); ++$tests;
$manualAscendingPending = CapitalSocialSorter::sort(
    $tiedPending, 'overview', 'pending', 'asc', 'name', 'asc'
);
check(array_column($manualAscendingPending,'contact_id') === [104,103,105,102,101],
    'Manual primary ascending sort keeps configured recency for both pending groups'); ++$tests;
$manualBalanceOrder = CapitalSocialSorter::sort(
    $tiedPending, 'overview', 'saldo_cents', 'desc', 'pending', 'desc'
);
check(array_column($manualBalanceOrder,'contact_id') === [104,101,102,103,105],
    'Manual primary balance sort preserves the secondary recency tie-break'); ++$tests;
$duplicateCriteria = CapitalSocialSorter::sort(
    $tiedPending, 'overview', null, null, 'pending', 'desc',
    [['column'=>'pending','direction'=>'asc'], ['column'=>'name','direction'=>'asc']]
);
check(array_column($duplicateCriteria,'contact_id') === [101,102,103,105,104],
    'Duplicate secondary columns are skipped without reversing the primary order'); ++$tests;
$ascendingRecency = CapitalSocialSorter::sort(
    $tiedPending, 'overview', 'last_contribution_date', 'asc'
);
check(array_column($ascendingRecency,'contact_id') === [103,101,104,102,105],
    'Missing integralizations remain last even when date sort is ascending'); ++$tests;
$invalidSavedOrder = CapitalSocialSorter::sort(
    $tiedPending, 'overview', 'hijack', 'asc', 'pending', 'desc',
    [['column'=>'drop_table','direction'=>'up'], ['column'=>['name'],'direction'=>'desc']]
);
check(array_column($invalidSavedOrder,'contact_id') === [102,101,104,103,105],
    'Invalid URL and additional preferences fall back to the complete safe three-level order'); ++$tests;
$identicalDate = [
    ['contact_id'=>5,'name'=>'Zulu', 'pending'=>0,'last_contribution_date'=>'2026-11-11'],
    ['contact_id'=>2,'name'=>'Alpha','pending'=>0,'last_contribution_date'=>'2026-11-11'],
    ['contact_id'=>1,'name'=>'Alpha','pending'=>0,'last_contribution_date'=>'2026-11-11'],
];
$identicalDefault = CapitalSocialSorter::sort($identicalDate, 'overview', null, null);
check(array_column($identicalDefault, 'contact_id') === [1,2,5],
    'Third default sorts equal-date records by name then a deterministic contact ID'); ++$tests;
$descendingThird = CapitalSocialSorter::sort(
    $identicalDate, 'overview', null, null, 'pending', 'desc',
    [['column'=>'last_contribution_date','direction'=>'desc'], ['column'=>'name','direction'=>'desc']]
);
check(array_column($descendingThird,'contact_id') === [5,1,2],
    'Third criterion direction is independently configurable'); ++$tests;
$mixedReviewRows = [
    ['contact_id'=>1001,'name'=>'Synthetic Pending Latest','pending'=>1,'warnings'=>0,'last_contribution_date'=>'2026-10-10'],
    ['contact_id'=>1002,'name'=>'Synthetic Pending Older','pending'=>1,'warnings'=>0,'last_contribution_date'=>'2021-01-08'],
    ['contact_id'=>1003,'name'=>'Alpha Clear','pending'=>0,'warnings'=>0,'last_contribution_date'=>'2026-11-11'],
    ['contact_id'=>1004,'name'=>'Zulu With Reservation','pending'=>0,'warnings'=>1,'last_contribution_date'=>'2026-11-11'],
    ['contact_id'=>1005,'name'=>'Older Clear','pending'=>0,'warnings'=>0,'last_contribution_date'=>'2026-02-01'],
];
$reviewFirst = CapitalSocialSorter::sort($mixedReviewRows,'overview',null,null);
check(array_column($reviewFirst,'contact_id') === [1001,1002,1004,1003,1005],
    'Default grouped status sorting must put confirmed reservations before clean members'); ++$tests;
$reviewManual = CapitalSocialSorter::sort($mixedReviewRows,'overview','pending','desc',
    'pending','desc', [
        ['column'=>'last_contribution_date','direction'=>'desc'],
        ['column'=>'name','direction'=>'asc'],
    ]);
check(array_column($reviewManual,'contact_id') === [1001,1002,1004,1003,1005],
    'Native descending review sort and saved recency must agree with visible badges'); ++$tests;
$reviewAscending = CapitalSocialSorter::sort($mixedReviewRows,'overview','pending','asc');
check(array_column($reviewAscending,'contact_id') === [1003,1005,1004,1001,1002],
    'Ascending review sort reverses unconfirmed and reservation severity'); ++$tests;
$reviewCustomRecency = CapitalSocialSorter::sort($mixedReviewRows,'overview',null,null,'pending','desc', [
    ['column'=>'name','direction'=>'asc'],
    ['column'=>'last_contribution_date','direction'=>'desc'],
]);
check(array_column($reviewCustomRecency,'contact_id') === [1001,1002,1004,1003,1005],
    'Custom secondary name sorting remains active within equal pending and reservation counts'); ++$tests;
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
    && str_contains($reportSource, "'name' => 'default_direction'")
    && str_contains($reportSource, "'name' => 'secondary_sort'")
    && str_contains($reportSource, "'name' => 'secondary_direction'")
    && str_contains($reportSource, "'name' => 'tertiary_sort'")
    && str_contains($reportSource, "'name' => 'tertiary_direction'"),
    'Native Akaunting editor exposes three configured sorting stages'); ++$tests;
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

$frraEntries = [
    ['contact_id'=>101,'name'=>'Cooperada Alfa','source'=>'production','competence'=>'2026-01',
     'amount_cents'=>10000,'status'=>'draft'],
    ['contact_id'=>101,'name'=>'Cooperada Alfa','source'=>'production','competence'=>'2026-01',
     'amount_cents'=>5000,'status'=>'paid'],
    ['contact_id'=>101,'name'=>'Cooperada Alfa','source'=>'annual','competence'=>'2026-01',
     'amount_cents'=>20000,'status'=>'draft'],
    ['contact_id'=>101,'name'=>'Cooperada Alfa','source'=>'annual','competence'=>'2026-02',
     'amount_cents'=>20000,'status'=>'draft'],
    ['contact_id'=>101,'name'=>'Cooperada Alfa','source'=>'annual','competence'=>'2026-09',
     'amount_cents'=>20200,'status'=>'draft'],
    ['contact_id'=>202,'name'=>'Cooperado Beta','source'=>'production','competence'=>'2026-12',
     'amount_cents'=>30000,'status'=>'paid'],
    ['contact_id'=>202,'name'=>'Cooperado Beta','source'=>'production','competence'=>'2025-12',
     'amount_cents'=>99000,'status'=>'paid'],
    ['contact_id'=>202,'name'=>'Cooperado Beta','source'=>'annual','competence'=>'2026-00',
     'amount_cents'=>10000,'status'=>'paid'],
];
$frra = \Modules\AkauntingReports\Support\FrraAnnualMatrix::build($frraEntries, 2026);
check($frra['members_count'] === 2 && $frra['total_cents'] === 85200,
    'FRRA month totals reconcile for the specified year without annual double counting'); ++$tests;
check($frra['production_cents'] === 45000 && $frra['annual_cents'] === 40200,
    'FRRA annual bill supplements only missing production competencies'); ++$tests;
check($frra['annual_overlap_months'] === 1 && $frra['overlap_difference_cents'] === 5000,
    'Duplicate annual/source month is excluded and disagreement surfaced'); ++$tests;
check($frra['monthlyTotals'] ?? null === null,
    'FRRA matrix exposes only defined monthly total schema'); ++$tests;
check($frra['months'][1] === 15000 && $frra['months'][2] === 20000 &&
    $frra['months'][9] === 20200 && $frra['months'][12] === 30000,
    'Competence drives exact January, February, September and December positions'); ++$tests;
check(count($frra['months']) === 12 &&
    array_sum($frra['months']) === array_sum(array_column($frra['members'], 'total_cents')),
    'FRRA twelve monthly totals equal all member annual totals'); ++$tests;
check($frra['members'][0]['months'][1] === 15000
    && $frra['members'][0]['sources'][2] === 'annual'
    && $frra['members'][1]['months'][12] === 30000,
    'FRRA matrix groups source amounts by correct contact and month'); ++$tests;
check($frra['draft_items'] === 3 && $frra['annual_only_months'] === 2,
    'FRRA rascunho and annual-only competencies remain visibly distinct'); ++$tests;
check(\Modules\AkauntingReports\Support\FrraAnnualMatrix::competence('Referente ao ano/mês: 2026-09') === '2026-09',
    'FRRA competence parsed from official document item description'); ++$tests;
foreach (['2026-00','2026-13','2026-9','2025-12-extra','','September 2026','2026-11','Referente ao ano/mês: 2026-00'] as $invalid) {
    check(\Modules\AkauntingReports\Support\FrraAnnualMatrix::competence($invalid) === null,
        'FRRA unverified competence must not silently be mapped to issue date'); ++$tests;
}
$dupe = [...$frraEntries,
    ['contact_id'=>101,'name'=>'Cooperada Alfa','source'=>'annual','competence'=>'2026-02',
     'amount_cents'=>10000,'status'=>'draft']];
$frraDupe = \Modules\AkauntingReports\Support\FrraAnnualMatrix::build($dupe,2026);
check($frraDupe['duplicate_annual_months'] === 1 && $frraDupe['months'][2] === 30000,
    'Multiple annual items for one month are explicitly flagged, not silently hidden'); ++$tests;
$frraEmpty=\Modules\AkauntingReports\Support\FrraAnnualMatrix::build([],2024);
check($frraEmpty['members_count'] === 0 && $frraEmpty['total_cents'] === 0
    && count($frraEmpty['months'])===12,
    'FRRA empty year remains a full zeroed twelve-month report'); ++$tests;

$availableFrraYears = \Modules\AkauntingReports\Support\FrraAnnualMatrix::yearOptions(
    $frraEntries,
    [2023 => ['items'=>2,'cents'=>700], 2024 => ['items'=>0,'cents'=>0]],
    2026
);
check($availableFrraYears === [2026=>'2026', 2025=>'2025', 2023=>'2023'],
    'Year filter only lists populated and explicitly identified historical FRRA years'); ++$tests;
check(!isset($availableFrraYears[2020]) && !isset($availableFrraYears[2024])
    && !isset($availableFrraYears[2027]),
    'Empty past, gap and future years are not offered by default'); ++$tests;
check(\Modules\AkauntingReports\Support\FrraAnnualMatrix::yearOptions([], [], 2026)
    === [2026=>'2026'],
    'A company without FRRA still offers the current year for an empty report'); ++$tests;
check(\Modules\AkauntingReports\Support\FrraAnnualMatrix::yearOptions(
    [], [2022 => ['items'=>1,'cents'=>0]], 2026
) === [2022=>'2022'],
    'A historical year with unresolved monthly competence remains selectable'); ++$tests;
check(\Modules\AkauntingReports\Support\FrraAnnualMatrix::yearOptions([
    ['contact_id'=>0,'source'=>'production','competence'=>'2021-05'],
    ['contact_id'=>10,'source'=>'other','competence'=>'2022-03'],
    ['contact_id'=>10,'source'=>'annual','competence'=>'2023-13'],
], [], 2026) === [2026=>'2026'],
    'Ineligible contacts, sources and malformed competences cannot invent year choices'); ++$tests;

$frraOptions = \Modules\AkauntingReports\Support\FrraAnnualMatrix::memberOptions($frraEntries, 2026);
check(count($frraOptions) === 2 && $frraOptions[101] === 'Cooperada Alfa'
    && $frraOptions[202] === 'Cooperado Beta',
    'Native FRRA member choices match only year-specific financial source contacts'); ++$tests;
check(\Modules\AkauntingReports\Support\FrraAnnualMatrix::memberOptions($frraEntries, 2025)
    === [202 => 'Cooperado Beta'],
    'Member selector must honor selected competence year'); ++$tests;
check(\Modules\AkauntingReports\Support\FrraAnnualMatrix::memberOptions($frraEntries, 2024) === [],
    'Year without FRRA has no unrelated vendor choices'); ++$tests;
$frraOutsider = [...$frraEntries, [
    'contact_id' => 303, 'name' => 'Estabelecimento fictício',
    'source' => 'unrelated_vendor', 'competence' => '2026-07',
    'amount_cents' => 50000, 'status' => 'paid',
]];
check(!array_key_exists(303, \Modules\AkauntingReports\Support\FrraAnnualMatrix::memberOptions($frraOutsider, 2026)),
    'Unrelated vendor must never be offered as FRRA member'); ++$tests;
$frraNames = [...$frraEntries, [
    'contact_id' => 303, 'name' => 'Cooperada Alfa',
    'source' => 'production', 'competence' => '2026-07',
    'amount_cents' => 1000, 'status' => 'paid',
]];
$disambiguated = \Modules\AkauntingReports\Support\FrraAnnualMatrix::memberOptions($frraNames, 2026);
check(count($disambiguated) === 3
    && $disambiguated[101] === 'Cooperada Alfa (cadastro #101)'
    && $disambiguated[303] === 'Cooperada Alfa (cadastro #303)'
    && count(array_unique(array_values($disambiguated))) === 3,
    'Distinct accounting contacts with same name must have unique labels, not be silently merged'); ++$tests;
$reportManifest = json_decode(file_get_contents(__DIR__ . '/../module.json'), true, 512, JSON_THROW_ON_ERROR);
check(in_array('Modules\\AkauntingReports\\Reports\\CapitalSocial', $reportManifest['reports'], true)
    && in_array('Modules\\AkauntingReports\\Reports\\FrraAnnual', $reportManifest['reports'], true),
    'FRRA is a second native report without removing the capital report'); ++$tests;
$frraView=file_get_contents(__DIR__ . '/../Resources/views/reports/frra-annual/content.blade.php');
check(str_contains($frraView, 'aria-label="Retenções anuais de FRRA por cooperado"')
    && str_contains($frraView, 'flex flex-wrap lg:flex-nowrap')
    && str_contains($frraView, 'range(0, 3)'),
    'FRRA list displays all twelve months in four responsive quarter groups'); ++$tests;
check(str_contains($frraView,'Total anual')
    && str_contains($frraView,'$class->summary[\'months\'][$month]'),
    'FRRA overview includes both member and monthly/annual footer totals'); ++$tests;
$frraSource=file_get_contents(__DIR__ . '/../Reports/FrraAnnual.php');
check(str_contains($frraSource, 'StreamedResponse')
    && str_contains($frraSource, "'Content-Type' => 'text/csv; charset=UTF-8'")
    && str_contains($frraSource, "FrraAnnualMatrix::memberOptions(")
    && str_contains($frraSource, "FrraAnnualMatrix::yearOptions(")
    && !str_contains($frraSource, 'min(2020, $current - 15)')
    && !str_contains($frraSource, "->where('type', 'vendor')"),
    'FRRA native report supports CSV and only audited FRRA members, never the full vendors directory'); ++$tests;
check(str_contains($frraSource, 'private ?array $sourceLedger = null;')
    && str_contains($frraSource, 'return $this->sourceLedger = FrraAnnualLedger::read($company, $policy);'),
    'FRRA filter and financial matrix reuse one company-scoped ledger'); ++$tests;
$frraLedgerSource=file_get_contents(__DIR__ . '/../Support/FrraAnnualLedger.php');
check(str_contains($frraLedgerSource, '->where(\'d.company_id\', $companyId)')
    && str_contains($frraLedgerSource, "->whereNull('d.deleted_at')")
    && str_contains($frraLedgerSource, "->whereNull('i.deleted_at')"),
    'FRRA ledger enforces company isolation and excludes deleted records'); ++$tests;
// Synthetic read-only database double exercises the whole ledger pathway.
// In particular, ambiguous historical values must not be labelled BRL if
// their documents use another currency.
final class SyntheticFrraQuery
{
    public function __call(string $name, array $args): self { return $this; }
    public function get(): array
    {
        $row = static function (int $id, string $currency, string $description, string $amount): object {
            return (object) [
                'item_id' => $id, 'document_id' => $id, 'document_number' => 'PDC-2026-' . $id,
                'contact_id' => 10, 'contact_name' => 'Synthetic member', 'current_contact_name' => null,
                'category_id' => 11, 'status' => 'draft', 'currency_code' => $currency,
                'item_name' => 'FRRA', 'description' => $description, 'total' => $amount,
            ];
        };
        return [
            $row(1, 'USD', 'Referente ao ano 2026 - meses de janeiro a março', '12.00'),
            $row(2, 'BRL', 'Referente ao ano 2026 - meses de janeiro a março', '7.00'),
            $row(3, 'EUR', 'Referente ao ano/mês: 2026-02', '4.00'),
            $row(4, 'BRL', 'Referente ao ano/mês: 2026-02', '3.00'),
        ];
    }
}
final class SyntheticFrraDb
{
    public static function table(string $name): SyntheticFrraQuery { return new SyntheticFrraQuery(); }
}
class_alias(SyntheticFrraDb::class, 'Illuminate\Support\Facades\DB');
require_once __DIR__ . '/../Support/FrraAnnualLedger.php';
$frraLedger = \Modules\AkauntingReports\Support\FrraAnnualLedger::read(1, [
    'production_category_id' => 11, 'annual_category_id' => 12,
    'production_prefix' => 'PDC-', 'annual_prefix' => 'FRRA-', 'item_name' => 'FRRA',
]);
check($frraLedger['non_brl_count'] === 2 && $frraLedger['undated_count'] === 1,
    'Foreign-currency FRRA is excluded before both dated and undated BRL aggregation'); ++$tests;
check($frraLedger['unallocated_by_year'][2026]['cents'] === 700
    && count($frraLedger['entries']) === 1 && $frraLedger['entries'][0]['amount_cents'] === 300,
    'Undated BRL summary and dated ledger exclude synthetic USD and EUR amounts'); ++$tests;

// FRRA sort preferences follow the native report editor without changing the ledger.
$frraSortRows = [
    ['contact_id'=>3, 'name'=>'Zeta', 'total_cents'=>2500, 'production_cents'=>2300, 'annual_cents'=>200],
    ['contact_id'=>1, 'name'=>'Alfa', 'total_cents'=>900, 'production_cents'=>0, 'annual_cents'=>900],
    ['contact_id'=>4, 'name'=>'Beta', 'total_cents'=>10000, 'production_cents'=>10000, 'annual_cents'=>0],
    ['contact_id'=>2, 'name'=>'Beta', 'total_cents'=>10000, 'production_cents'=>10000, 'annual_cents'=>0],
];
$sorter = \Modules\AkauntingReports\Support\FrraAnnualSorter::class;
check($sorter::defaults(null, null) === ['name', 'asc'],
    'FRRA defaults to cooperative member alphabetical ascending'); ++$tests;
check($sorter::defaults('total_cents', 'desc') === ['total_cents', 'desc']
    && $sorter::defaults('bad_column', 'invalid') === ['name', 'asc'],
    'FRRA native editor validates saved order and direction'); ++$tests;
check(array_keys($sorter::options()) === ['name','total_cents','production_cents','annual_cents'],
    'FRRA offers member name, annual total and both audited financial sources'); ++$tests;
$sorted = $sorter::sort($frraSortRows, null, null);
check(array_column($sorted, 'contact_id') === [1,2,4,3],
    'FRRA default is alphabetical, deterministic for duplicate names'); ++$tests;
check(array_column($sorter::sort($frraSortRows, 'total_cents', 'desc'), 'contact_id') === [2,4,3,1],
    'FRRA annual amount sorts numerically in descending order'); ++$tests;
check(array_column($sorter::sort($frraSortRows, 'production_cents', 'asc'), 'contact_id') === [1,3,2,4]
    && array_column($sorter::sort($frraSortRows, 'annual_cents', 'desc'), 'contact_id') === [1,3,2,4],
    'FRRA production and annual supplements are sortable independently'); ++$tests;
check(array_column($sorter::sort($frraSortRows, null, null, 'total_cents', 'desc'), 'contact_id') === [2,4,3,1]
    && array_column($sorter::sort($frraSortRows, 'name', 'asc', 'total_cents', 'desc'), 'contact_id') === [1,2,4,3],
    'FRRA manual native sorting takes precedence over saved preference'); ++$tests;
check(array_column($sorter::sort($frraSortRows, 'bad_column', 'desc', 'name', 'asc'), 'contact_id') === [1,2,4,3],
    'FRRA unknown query columns cannot bypass sorting allowlist'); ++$tests;
check(array_sum(array_column($sorter::sort($frraSortRows, 'total_cents', 'desc'), 'total_cents'))
    === array_sum(array_column($frraSortRows, 'total_cents')),
    'FRRA sorting never changes financial totals'); ++$tests;
$frraReportCode = file_get_contents(__DIR__ . '/../Reports/FrraAnnual.php');
$frraViewCode = file_get_contents(__DIR__ . '/../Resources/views/reports/frra-annual/content.blade.php');
check(str_contains($frraReportCode, "'name' => 'default_sort'")
    && str_contains($frraReportCode, "'name' => 'default_direction'")
    && str_contains($frraReportCode, 'FrraAnnualSorter::sort('),
    'FRRA report has native saved sorting preferences connected to display and CSV'); ++$tests;
check(str_contains($frraViewCode, '<x-sortablelink column="name" title="Cooperado" />')
    && str_contains($frraViewCode, '<x-sortablelink column="total_cents" title="Total anual" />')
    && str_contains($frraViewCode, '<x-sortablelink column="production_cents"')
    && str_contains($frraViewCode, '<x-sortablelink column="annual_cents"'),
    'FRRA responsive view exposes native sortable links to all allowed columns'); ++$tests;

echo "$tests business invariants passed\n";
