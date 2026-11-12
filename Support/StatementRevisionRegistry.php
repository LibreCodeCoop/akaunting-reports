<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

use InvalidArgumentException;

/**
 * Pure append-only version staging. Storage and approval belong to a
 * separately authorized component; reporting code never writes records.
 */
final class StatementRevisionRegistry
{
    /**
     * @param list<array{snapshot:array<string,mixed>,state:string}> $existing
     * @param array<string,mixed> $input
     * @return list<array{snapshot:array<string,mixed>,state:string}>
     */
    public static function stage(array $existing, array $input): array
    {
        $candidate = StatementSnapshot::normalize($input);
        $duplicate = false;
        $seen = [];
        foreach ($existing as $entry) {
            if (!is_array($entry) || !isset($entry['snapshot'], $entry['state'])
                || !in_array($entry['state'], ['pending_review', 'approved', 'rejected'], true)) {
                throw new InvalidArgumentException('Malformed statement revision record.');
            }
            $current = StatementSnapshot::normalize($entry['snapshot']);
            $key = self::key($current);
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Duplicate existing statement revision.');
            }
            $seen[$key] = true;
            if ($key !== self::key($candidate)) {
                continue;
            }
            if ($current !== $candidate) {
                throw new InvalidArgumentException('Revision collision: immutable source evidence cannot be overwritten.');
            }
            $duplicate = true;
        }
        if (!$duplicate) {
            $existing[] = ['snapshot' => $candidate, 'state' => 'pending_review'];
        }
        usort($existing, static fn (array $left, array $right): int =>
            strcmp(self::key($left['snapshot']), self::key($right['snapshot']))
        );
        return $existing;
    }

    /** @param array<string,mixed> $snapshot */
    private static function key(array $snapshot): string
    {
        return implode(':', [
            (string) $snapshot['company_id'],
            (string) $snapshot['statement_id'],
            (string) $snapshot['revision'],
        ]);
    }
}
