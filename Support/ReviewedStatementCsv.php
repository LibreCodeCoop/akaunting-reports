<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

use InvalidArgumentException;

/** Strict read-only adapter for human-reviewed CSV account rows. */
final class ReviewedStatementCsv
{
    private const FIELDS = ['code', 'name', 'row_type', 'opening', 'debit', 'credit', 'closing'];

    /**
     * @param array<string,mixed> $metadata StatementSnapshot v1 metadata, including source_sha256.
     * @param array<string,string> $columns Canonical field => external CSV header.
     * @return array<string,mixed>
     */
    public static function parse(
        string $csv,
        array $metadata,
        array $columns,
        string $delimiter = ',',
        string $decimalSeparator = '.'
    ): array {
        if ($csv === '' || strlen($csv) > 2_097_152) {
            throw new InvalidArgumentException('CSV must be nonempty and at most 2 MiB.');
        }
        if (!in_array($delimiter, [',', ';', "\t"], true)
            || !in_array($decimalSeparator, ['.', ','], true)) {
            throw new InvalidArgumentException('Unsupported CSV locale.');
        }
        if (array_keys($columns) !== self::FIELDS) {
            throw new InvalidArgumentException('All canonical columns must be mapped in order.');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '' || trim($column) !== $column) {
                throw new InvalidArgumentException('Invalid CSV column mapping.');
            }
        }
        if (count(array_unique(array_values($columns))) !== count($columns)) {
            throw new InvalidArgumentException('Duplicate CSV column mapping.');
        }
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new InvalidArgumentException('Cannot open temporary CSV reader.');
        }
        try {
            fwrite($stream, $csv);
            rewind($stream);
            $headers = fgetcsv($stream, 0, $delimiter, '"', '');
            if (!is_array($headers) || $headers === []) {
                throw new InvalidArgumentException('CSV header is missing.');
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
            if (count(array_unique($headers)) !== count($headers)) {
                throw new InvalidArgumentException('Duplicate CSV header names.');
            }
            $indices = [];
            foreach ($columns as $field => $name) {
                $index = array_search($name, $headers, true);
                if ($index === false) {
                    throw new InvalidArgumentException('Missing mapped CSV column: ' . $field);
                }
                $indices[$field] = $index;
            }
            $accounts = [];
            while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                if ($row === [null] || $row === ['']) {
                    continue;
                }
                if (count($row) !== count($headers)) {
                    throw new InvalidArgumentException('Malformed CSV row width.');
                }
                if (count($accounts) >= 10_000) {
                    throw new InvalidArgumentException('CSV has too many account rows.');
                }
                if (trim((string) $row[$indices['row_type']]) !== 'leaf') {
                    throw new InvalidArgumentException('Only reviewed leaf-account rows are accepted.');
                }
                $accounts[] = [
                    'code' => (string) $row[$indices['code']],
                    'name' => (string) $row[$indices['name']],
                    'opening_cents' => self::cents((string) $row[$indices['opening']], $decimalSeparator),
                    'debit_cents' => self::cents((string) $row[$indices['debit']], $decimalSeparator),
                    'credit_cents' => self::cents((string) $row[$indices['credit']], $decimalSeparator),
                    'closing_cents' => self::cents((string) $row[$indices['closing']], $decimalSeparator),
                ];
            }
        } finally {
            fclose($stream);
        }
        // Only the trusted importing boundary can verify source_sha256
        // against the retained original file. No approval is inferred here.
        $metadata['accounts'] = $accounts;
        return StatementSnapshot::normalize($metadata);
    }

    public static function cents(string $amount, string $decimalSeparator = '.'): int
    {
        if (!in_array($decimalSeparator, ['.', ','], true)) {
            throw new InvalidArgumentException('Unsupported decimal separator.');
        }
        $pattern = $decimalSeparator === ','
            ? '/^(-?)(0|[1-9][0-9]{0,13})(?:,([0-9]{1,2}))?$/D'
            : '/^(-?)(0|[1-9][0-9]{0,13})(?:\.([0-9]{1,2}))?$/D';
        if (preg_match($pattern, $amount, $match) !== 1) {
            throw new InvalidArgumentException('Ambiguous, imprecise or out-of-range monetary amount.');
        }
        $minor = ((int) $match[2] * 100) + (int) str_pad($match[3] ?? '', 2, '0');
        return $match[1] === '-' ? -$minor : $minor;
    }
}
