<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use InvalidArgumentException;

/**
 * One finding, never an accounting mutation. IDs are stable for the same rule,
 * company, source, case and competence, even when evidence changes.
 *
 * Evidence contains only normalized numbers, flags and machine codes. Never
 * include names, tax numbers, free-text descriptions or credentials.
 */
final readonly class Occurrence
{
    public const CONFIRMED = 'confirmed';
    public const SUSPECTED = 'suspected';
    public const INSUFFICIENT = 'insufficient';

    private string $id;

    /** @param array<string,int|bool|string> $evidence */
    public function __construct(
        public int $companyId,
        public string $ruleId,
        public int $ruleVersion,
        public string $classification,
        public string $severity,
        public string $source,
        public string $sourceId,
        public string $caseKey,
        public ?string $competence,
        public array $evidence,
        public string $explanationKey,
        public string $guidanceKey,
    ) {
        if ($companyId <= 0 || $ruleVersion <= 0
            || preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $ruleId) !== 1
            || !in_array($classification, [self::CONFIRMED, self::SUSPECTED, self::INSUFFICIENT], true)
            || !in_array($severity, ['critical', 'warning', 'info'], true)
            || preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $source) !== 1
            || preg_match('/^[a-zA-Z0-9:_-]{1,96}$/D', $sourceId) !== 1
            || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $caseKey) !== 1
            || ($competence !== null && preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D', $competence) !== 1)
            || preg_match('/^audit\.[a-z0-9_.]+$/D', $explanationKey) !== 1
            || preg_match('/^audit\.[a-z0-9_.]+$/D', $guidanceKey) !== 1) {
            throw new InvalidArgumentException('Invalid audit occurrence contract.');
        }
        if (count($evidence) > 16) {
            throw new InvalidArgumentException('Too many audit evidence fields.');
        }
        foreach ($evidence as $key => $value) {
            if (!is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $key) !== 1
                || preg_match('/name|email|phone|address|tax_number|description|notes|raw|contact/i', $key)
                || !(is_int($value) || is_bool($value)
                    || (is_string($value) && preg_match('/^[a-z][a-z0-9_-]{0,39}$/D', $value) === 1))) {
                throw new InvalidArgumentException('Audit evidence must be non-identifying machine data.');
            }
        }
        $this->id = hash('sha256', json_encode(
            [$companyId, $ruleId, $ruleVersion, $source, $sourceId, $caseKey, $competence],
            JSON_THROW_ON_ERROR
        ));
    }

    public function id(): string
    {
        return $this->id;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->companyId,
            'rule_id' => $this->ruleId,
            'rule_version' => $this->ruleVersion,
            'classification' => $this->classification,
            'severity' => $this->severity,
            'source' => $this->source,
            'source_id' => $this->sourceId,
            'case_key' => $this->caseKey,
            'competence' => $this->competence,
            'evidence' => $this->evidence,
            'explanation_key' => $this->explanationKey,
            'guidance_key' => $this->guidanceKey,
        ];
    }
}
