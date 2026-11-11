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
echo "$tests business invariants passed\n";
