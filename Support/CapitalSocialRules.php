<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

final class CapitalSocialRules
{
    /** @return array{kind:string,amount_cents:int,reason:string} */
    public static function transaction(array $record, CapitalSocialPolicy $policy): array
    {
        $amount = Money::cents((string) $record['amount']);
        $description = (string) ($record['description'] ?? '');
        $categoryCode = (string) ($record['category_code'] ?? '');
        $isRefund = $categoryCode === $policy->refundCategoryCode;
        $looksLikeRefund = (bool) preg_match('/devolu|restitui|estorn|pag[oa] a maior/ui', $description);

        if ((string) $record['currency_code'] !== 'BRL' || $amount <= 0) {
            return self::review('Currency or amount needs verification.');
        }
        if ((string) $record['type'] === 'income'
            && (int) $record['category_id'] === $policy->categoryId
            && !$looksLikeRefund && !$isRefund) {
            return ['kind' => 'integralizacao', 'amount_cents' => $amount, 'reason' => ''];
        }
        if ((string) $record['type'] === 'expense' && $isRefund) {
            return ['kind' => 'devolucao', 'amount_cents' => -$amount, 'reason' => ''];
        }
        return self::review('Transaction has insufficient or ambiguous capital classification.');
    }

    /**
     * $document contains computed financial totals in cents, the number of capital lines and non-capital lines.
     * @return array{kind:string,amount_cents:int,reason:string}
     */
    public static function document(array $document): array
    {
        $total = (int) $document['document_amount_cents'];
        $paid = (int) $document['paid_cents'];
        $capital = (int) $document['capital_cents'];
        $capitalLines = (int) $document['capital_lines'];
        $itemLines = (int) $document['item_lines'];
        $hasPayment = (bool) $document['has_payment'];
        $type = (string) $document['type'];
        $status = (string) ($document['status'] ?? '');

        if (in_array($status, ['cancelled', 'canceled', 'void'], true)) {
            return self::review('Cancelled or voided document must be reconciled separately.');
        }
        if ($document['currency_code'] !== 'BRL') {
            return self::review('Foreign-currency document requires verified conversion.');
        }
        if (!in_array($type, ['invoice', 'bill'], true)) {
            return self::review('Unsupported document type.');
        }
        if (!$hasPayment) {
            return self::review('Unpaid document: capital is not yet confirmed.');
        }
        if ($total > 0 && $paid < $total) {
            return self::review('Partially paid document: capital allocation needs review.');
        }
        if ($total > 0 && $paid > $total + 1) {
            return self::review('Payments exceed the document amount.');
        }
        if ($type === 'invoice' && $capital > 0) {
            return ['kind' => 'integralizacao', 'amount_cents' => $capital, 'reason' => ''];
        }
        if ($type === 'bill' && $capital < 0 && $capitalLines > 0) {
            return ['kind' => 'retencao', 'amount_cents' => -$capital, 'reason' => ''];
        }
        if ($type === 'bill' && $capital > 0 && $capitalLines > 0 && $itemLines === $capitalLines) {
            return ['kind' => 'devolucao', 'amount_cents' => -$capital, 'reason' => ''];
        }
        return self::review('Document contains ambiguous capital items or direction.');
    }

    /** @return array{kind:string,amount_cents:int,reason:string} */
    private static function review(string $reason): array
    {
        return ['kind' => 'revisao', 'amount_cents' => 0, 'reason' => $reason];
    }
}
