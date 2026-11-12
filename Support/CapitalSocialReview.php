<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

/** Translate stable financial review classifications at the presentation boundary. */
final class CapitalSocialReview
{
    private const UNPAID = 'Unpaid document: capital is not yet confirmed.';

    private const REASON_KEYS = [
        self::UNPAID => 'waiting_payment',
        'Partially paid document: capital allocation needs review.' => 'review_partial',
        'Capital-related transaction references a non-capital document.' => 'review_link',
        'Cancelled or voided document must be reconciled separately.' => 'review_cancelled',
        'Payments exceed the document amount.' => 'review_payments',
        'Foreign-currency document requires verified conversion.' => 'review_conversion',
        'Currency or amount needs verification.' => 'review_currency',
        'Transaction has insufficient or ambiguous capital classification.' => 'review_classification',
        'Unsupported document type.' => 'review_type',
        'Document contains ambiguous capital items or direction.' => 'review_items',
    ];

    private const SHORT_KEYS = [
        'waiting_payment' => 'payment_pending_short',
        'review_link' => 'review_link_short',
        'confirmed_reservation' => 'confirmed_reservation_short',
        'review_conversion' => 'review_conversion_short',
        'review_currency' => 'review_currency_short',
        'review_classification' => 'review_classification_short',
        'review_type' => 'review_type_short',
        'review_items' => 'review_items_short',
    ];

    private const GUIDANCE_KEYS = [
        self::UNPAID => 'payment_help',
        'Partially paid document: capital allocation needs review.' => 'guidance_partial',
        'Capital-related transaction references a non-capital document.' => 'guidance_document_link',
        'Cancelled or voided document must be reconciled separately.' => 'guidance_cancelled',
        'Payments exceed the document amount.' => 'guidance_excess_payment',
        'Foreign-currency document requires verified conversion.' => 'review_currency_help',
        'Currency or amount needs verification.' => 'review_currency_help',
    ];

    public static function category(array $row): string
    {
        if (($row['status'] ?? '') === 'confirmado') {
            return 'confirmed';
        }
        return ($row['reason'] ?? '') === self::UNPAID ? 'awaiting_payment' : 'requires_review';
    }

    public static function label(array $row): string
    {
        if (($row['status'] ?? '') === 'confirmado') {
            $key = empty($row['reason']) ? 'confirmed' : 'confirmed_reservation';
        } else {
            $key = self::REASON_KEYS[(string) ($row['reason'] ?? '')] ?? 'review_required';
        }
        return trans('akaunting-reports::ui.' . $key);
    }

    public static function shortLabel(string $label): string
    {
        foreach (self::SHORT_KEYS as $key => $short) {
            if ($label === trans('akaunting-reports::ui.' . $key)) {
                return trans('akaunting-reports::ui.' . $short);
            }
        }
        return $label;
    }

    public static function guidance(array $row): string
    {
        if (($row['status'] ?? '') === 'confirmado') {
            return trans('akaunting-reports::ui.' .
                (empty($row['reason']) ? 'confirmed_activities' : 'confirmed_warning_help'));
        }
        $key = self::GUIDANCE_KEYS[(string) ($row['reason'] ?? '')] ?? 'review_source_help';
        return trans('akaunting-reports::ui.' . $key);
    }

    public static function reason(string $reason): string
    {
        if ($reason === '') {
            return '';
        }
        $key = self::REASON_KEYS[$reason] ?? null;
        return $key === null ? $reason : trans('akaunting-reports::ui.' . $key);
    }

    /** @return array{confirmed:int,awaiting_payment:int,requires_review:int} */
    public static function counts(array $movements): array
    {
        $counts = ['confirmed' => 0, 'awaiting_payment' => 0, 'requires_review' => 0];
        foreach ($movements as $row) {
            ++$counts[self::category($row)];
        }
        return $counts;
    }
}
