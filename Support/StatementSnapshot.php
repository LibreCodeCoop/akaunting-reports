<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

/**
 * Read-only canonical contract for human-reviewed accounting snapshots.
 * Credit balances are negative; closing = opening + debits - credits.
 * Original source files remain outside this module.
 */
final class StatementSnapshot
{
    /** @param array<string,mixed> $input
     *  @return array<string,mixed>
     */
    public static function normalize(array $input): array
    {
        foreach (['company_id', 'statement_id', 'revision', 'period_start', 'period_end', 'currency', 'source_sha256', 'accounts'] as $field) {
            if (!array_key_exists($field, $input)) {
                throw new \InvalidArgumentException("Missing statement field: {$field}");
            }
        }
        if (($input['schema_version'] ?? null) !== 1) {
            throw new \InvalidArgumentException('Unsupported statement schema version.');
        }
        if (!is_int($input['company_id']) || $input['company_id'] <= 0) {
            throw new \InvalidArgumentException('Invalid company ID.');
        }
        foreach (['statement_id', 'revision'] as $field) {
            if (!is_string($input[$field]) || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,99}$/D', $input[$field]) !== 1) {
                throw new \InvalidArgumentException("Invalid {$field}.");
            }
        }
        $start = self::date($input['period_start'], 'period_start');
        $end = self::date($input['period_end'], 'period_end');
        if ($start > $end) {
            throw new \InvalidArgumentException('Statement period is reversed.');
        }
        if (!is_string($input['currency']) || preg_match('/^[A-Z]{3}$/D', $input['currency']) !== 1) {
            throw new \InvalidArgumentException('Invalid currency.');
        }
        if (!is_string($input['source_sha256']) || preg_match('/^[a-f0-9]{64}$/D', $input['source_sha256']) !== 1) {
            throw new \InvalidArgumentException('Source SHA-256 is required.');
        }
        if (!is_array($input['accounts']) || !array_is_list($input['accounts']) || $input['accounts'] === []) {
            throw new \InvalidArgumentException('Accounts must be a nonempty list.');
        }
        $accounts = [];
        foreach ($input['accounts'] as $account) {
            if (!is_array($account)) {
                throw new \InvalidArgumentException('Invalid account row.');
            }
            $code = $account['code'] ?? null;
            if (!is_string($code) || $code === '' || strlen($code) > 100 || trim($code) !== $code) {
                throw new \InvalidArgumentException('Invalid account code.');
            }
            if (isset($accounts[$code])) {
                throw new \InvalidArgumentException('Duplicate account code.');
            }
            $name = $account['name'] ?? null;
            if (!is_string($name) || trim($name) === '' || strlen($name) > 255) {
                throw new \InvalidArgumentException('Invalid account name.');
            }
            foreach (['opening_cents', 'debit_cents', 'credit_cents', 'closing_cents'] as $field) {
                if (!isset($account[$field]) || !is_int($account[$field])) {
                    throw new \InvalidArgumentException("{$field} must be integer minor units.");
                }
            }
            if ($account['debit_cents'] < 0 || $account['credit_cents'] < 0) {
                throw new \InvalidArgumentException('Debits and credits cannot be negative.');
            }
            $expected = $account['opening_cents'] + $account['debit_cents'] - $account['credit_cents'];
            if ($expected !== $account['closing_cents']) {
                throw new \InvalidArgumentException("Unbalanced account: {$code}");
            }
            $accounts[$code] = [
                'code' => $code,
                'name' => trim($name),
                'opening_cents' => $account['opening_cents'],
                'debit_cents' => $account['debit_cents'],
                'credit_cents' => $account['credit_cents'],
                'closing_cents' => $account['closing_cents'],
            ];
        }
        ksort($accounts, SORT_STRING);
        return [
            'schema_version' => 1,
            'company_id' => $input['company_id'],
            'statement_id' => $input['statement_id'],
            'revision' => $input['revision'],
            'period_start' => $start,
            'period_end' => $end,
            'currency' => $input['currency'],
            'source_sha256' => $input['source_sha256'],
            'accounts' => array_values($accounts),
        ];
    }

    private static function date(mixed $value, string $field): string
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            throw new \InvalidArgumentException("Invalid {$field}.");
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException("Invalid {$field}.");
        }
        return $value;
    }
}
