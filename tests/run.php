<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once __DIR__ . '/../Support/Money.php';
require_once __DIR__ . '/../Support/CapitalSocialPolicy.php';
require_once __DIR__ . '/../Support/CapitalSocialRules.php';
require_once __DIR__ . '/../Support/CapitalSocialSummary.php';

use Modules\AkauntingReports\Support\Money;
use Modules\AkauntingReports\Support\CapitalSocialPolicy;
use Modules\AkauntingReports\Support\CapitalSocialRules;
use Modules\AkauntingReports\Support\CapitalSocialSummary;

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
$reportSource = file_get_contents(__DIR__ . '/../Reports/CapitalSocial.php');
check(str_contains($reportSource, "parent::setViews()")
    && !str_contains($reportSource, "views['show'] ="),
    'Core Akaunting report layout is not replaced'); ++$tests;
check(str_contains($reportSource, "views['capital']") && str_contains($reportSource, 'getAsOfDate()'),
    'Native report content and as-of filter are wired'); ++$tests;
check(is_file(__DIR__ . '/../Resources/views/reports/capital-social/content.blade.php')
    && !is_file(__DIR__ . '/../Resources/views/reports/capital-social/show.blade.php'),
    'Native report is not shadowed by an independent admin page'); ++$tests;
echo "$tests business invariants passed\n";
