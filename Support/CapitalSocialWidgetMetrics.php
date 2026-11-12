<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

/** Only confirmed movements contribute to the displayed capital balance. */
final class CapitalSocialWidgetMetrics
{
    /**
     * @param list<array<string,mixed>> $movements
     * @return array{total_cents:int,pending_count:int,warnings_count:int,members_count:int}
     */
    public static function calculate(array $movements): array
    {
        $summary = CapitalSocialSummary::calculate($movements);

        return [
            'total_cents' => $summary['total_cents'],
            'pending_count' => $summary['pending_count'],
            'warnings_count' => $summary['warnings_count'],
            'members_count' => $summary['members_count'],
        ];
    }
}
