<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use InvalidArgumentException;

/** Versioned, explicit registry; a rule may never silently shadow another. */
final readonly class Catalog
{
    public const VERSION = 2;

    /** @param list<Rule> $rules */
    public function __construct(private array $rules)
    {
        $ids = [];
        foreach ($rules as $rule) {
            if (!($rule instanceof Rule)
                || preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $rule->id()) !== 1
                || $rule->version() <= 0 || isset($ids[$rule->id()])
                || $rule->sources() === [] || !array_is_list($rule->sources())
                || count(array_unique($rule->sources())) !== count($rule->sources())) {
                throw new InvalidArgumentException('Invalid or duplicated audit rule.');
            }
            foreach ($rule->sources() as $source) {
                if (!is_string($source) || preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $source) !== 1) {
                    throw new InvalidArgumentException('Invalid audit rule source.');
                }
            }
            $ids[$rule->id()] = true;
        }
    }

    /** @return list<Rule> */
    public function rules(): array
    {
        return $this->rules;
    }

    /** @return list<array{id:string,version:int,sources:list<string>}> */
    public function manifest(): array
    {
        $result = [];
        foreach ($this->rules as $rule) {
            $result[] = ['id' => $rule->id(), 'version' => $rule->version(), 'sources' => $rule->sources()];
        }
        usort($result, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        return $result;
    }
}
