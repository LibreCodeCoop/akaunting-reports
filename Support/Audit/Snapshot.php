<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use InvalidArgumentException;
use LogicException;

/**
 * An authorized, company-scoped read snapshot built by an adapter.
 * A missing or unavailable source is NOT equivalent to an empty source.
 */
final readonly class Snapshot
{
    /**
     * @param array<string,array{available:bool,rows:list<array<string,mixed>>}> $sources
     */
    public function __construct(
        public int $companyId,
        public string $currencyCode,
        private array $sources,
    ) {
        if ($companyId <= 0 || preg_match('/^[A-Z]{3}$/D', $currencyCode) !== 1) {
            throw new InvalidArgumentException('Invalid audit company or currency.');
        }
        foreach ($sources as $name => $source) {
            if (!is_string($name) || preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $name) !== 1
                || !is_array($source) || !array_key_exists('available', $source)
                || !is_bool($source['available']) || !isset($source['rows'])
                || !is_array($source['rows']) || !array_is_list($source['rows'])
                || (!$source['available'] && $source['rows'] !== [])) {
                throw new InvalidArgumentException('Invalid audit source descriptor.');
            }
            foreach ($source['rows'] as $row) {
                if (!is_array($row) || !isset($row['company_id'])
                    || !is_int($row['company_id']) || $row['company_id'] !== $companyId) {
                    throw new InvalidArgumentException('Cross-company or unscoped audit source.');
                }
            }
        }
    }

    public function available(string $source): bool
    {
        return ($this->sources[$source]['available'] ?? false) === true;
    }

    /** @return list<array<string,mixed>> */
    public function rows(string $source): array
    {
        if (!$this->available($source)) {
            throw new LogicException('Audit source is unavailable.');
        }
        return $this->sources[$source]['rows'];
    }
}
