<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

use InvalidArgumentException;

/**
 * Read-only eligibility decisions for assembly allocation evidence.
 * These are NOT ledger movements and must not be added to totals directly.
 */
final class CapitalAssemblyEvidence
{
    /**
     * @param list<array<string,mixed>> $events Confidential, per-member source allocations.
     * @param list<string> $alreadyRecognized Stable unique source keys already included elsewhere.
     * @return list<array{source_key:string,eligible:bool,reason:string,kind:string,amount_cents:int}>
     */
    public static function assess(array $events, int $companyId, array $alreadyRecognized = []): array
    {
        if ($companyId <= 0) {
            throw new InvalidArgumentException('An active company is required.');
        }
        $seen = array_fill_keys($alreadyRecognized, true);
        $decisions = [];
        foreach ($events as $event) {
            if (!is_array($event) || ($event['company_id'] ?? null) !== $companyId
                || !is_int($event['contact_id'] ?? null) || $event['contact_id'] <= 0
                || !is_int($event['amount_cents'] ?? null) || $event['amount_cents'] === 0) {
                throw new InvalidArgumentException('Invalid or cross-company assembly allocation.');
            }
            foreach (['deliberation_ref', 'source_revision', 'allocation_ref'] as $field) {
                if (!is_string($event[$field] ?? null)
                    || preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,99}$/D', $event[$field]) !== 1) {
                    throw new InvalidArgumentException('Missing stable assembly evidence identifier.');
                }
            }
            $sha = $event['source_sha256'] ?? null;
            if (!is_string($sha) || preg_match('/^[a-f0-9]{64}$/D', $sha) !== 1) {
                throw new InvalidArgumentException('Missing immutable source hash.');
            }
            $kind = $event['kind'] ?? null;
            if (!in_array($kind, ['capitalization', 'reversal'], true)
                || ($kind === 'capitalization' && $event['amount_cents'] < 0)
                || ($kind === 'reversal' && $event['amount_cents'] > 0)) {
                throw new InvalidArgumentException('Invalid capital allocation sign or kind.');
            }
            $key = implode(':', [
                $companyId, $event['deliberation_ref'],
                $event['allocation_ref'], $event['contact_id'],
            ]);
            $isDuplicate = isset($seen[$key]);
            $approval = $event['review_state'] ?? null;
            if (!in_array($approval, ['pending_review', 'approved', 'rejected'], true)) {
                throw new InvalidArgumentException('Unrecognized accounting approval state.');
            }
            $entryRef = $event['accounting_entry_ref'] ?? null;
            $hasEntry = is_string($entryRef) && trim($entryRef) !== '';
            // A reversal must link to a previously recognized event. This does not
            // prove the reference: a trusted source adapter must verify it.
            $reversalRef = $event['reverses_source_key'] ?? null;
            $reversalLinked = $kind !== 'reversal' || (
                is_string($reversalRef) && in_array($reversalRef, $alreadyRecognized, true)
            );
            $reason = $isDuplicate ? 'duplicate_source'
                : ($approval !== 'approved' ? 'requires_accountant_review'
                : (!$hasEntry ? 'missing_accounting_entry'
                : (!$reversalLinked ? 'unverified_reversal' : 'eligible')));
            // A pending or rejected document must not consume the identity of a
            // later approved revision of the same allocation. Only an eligible
            // recognition reserves the key within this batch.
            if ($reason === 'eligible') {
                $seen[$key] = true;
            }
            $decisions[] = [
                'source_key' => $key,
                'eligible' => $reason === 'eligible',
                'reason' => $reason,
                'kind' => $kind,
                'amount_cents' => $event['amount_cents'],
            ];
        }
        return $decisions;
    }
}
