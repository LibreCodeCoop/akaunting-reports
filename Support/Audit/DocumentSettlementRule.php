<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support\Audit;

use Modules\AkauntingReports\Support\Money;
use UnexpectedValueException;

/**
 * Conservative reconciliation of linked transactions, never a balance repair.
 * A status mismatch is a suspicion until a human confirms the source workflow.
 */
final class DocumentSettlementRule implements Rule
{
    #[\Override]
    public function id(): string { return 'document_settlement'; }

    #[\Override]
    public function version(): int { return 1; }

    #[\Override]
    public function sources(): array { return ['documents', 'transactions']; }

    /** @return list<Occurrence> */
    #[\Override]
    public function inspect(Snapshot $snapshot): array
    {
        $byDocument = [];
        foreach ($snapshot->rows('transactions') as $transaction) {
            $documentId = (int) ($transaction['document_id'] ?? 0);
            if ($documentId <= 0) {
                continue; // Standalone transactions are not by themselves irregular.
            }
            $byDocument[$documentId][] = $transaction;
        }

        $findings = [];
        foreach ($snapshot->rows('documents') as $document) {
            $id = (int) ($document['id'] ?? 0);
            $type = (string) ($document['type'] ?? '');
            $status = (string) ($document['status'] ?? '');
            $currency = (string) ($document['currency_code'] ?? '');
            if ($id <= 0 || !in_array($type, ['invoice', 'bill'], true)) {
                continue;
            }
            $linked = $byDocument[$id] ?? [];
            $args = [$snapshot->companyId, $id, $document['competence'] ?? null];
            if ($currency !== $snapshot->currencyCode) {
                if ($linked !== []) {
                    $findings[] = $this->finding(...$args, case: 'foreign_currency',
                        classification: Occurrence::INSUFFICIENT, severity: 'info',
                        evidence: ['linked_count' => count($linked)]);
                }
                continue;
            }
            try {
                $total = Money::cents((string) ($document['amount'] ?? ''));
                if ($total < 0) {
                    throw new UnexpectedValueException('Negative total.');
                }
            } catch (UnexpectedValueException) {
                $findings[] = $this->finding(...$args, case: 'invalid_amount',
                    classification: Occurrence::INSUFFICIENT, severity: 'info', evidence: []);
                continue;
            }
            $paid = 0;
            $valid = 0;
            $unsupported = false;
            $expectedType = $type === 'invoice' ? 'income' : 'expense';
            foreach ($linked as $payment) {
                if ((string) ($payment['currency_code'] ?? '') !== $currency) {
                    $unsupported = true;
                    continue;
                }
                if ((string) ($payment['type'] ?? '') !== $expectedType) {
                    $unsupported = true;
                    continue;
                }
                try {
                    $amount = Money::cents((string) ($payment['amount'] ?? ''));
                } catch (UnexpectedValueException) {
                    $unsupported = true;
                    continue;
                }
                if ($amount < 0) {
                    $unsupported = true;
                    continue;
                }
                $paid += $amount;
                ++$valid;
            }
            if ($unsupported) {
                $findings[] = $this->finding(...$args, case: 'unsupported_payments',
                    classification: Occurrence::INSUFFICIENT, severity: 'info',
                    evidence: ['linked_count' => count($linked), 'valid_count' => $valid]);
                continue; // Never claim reconciliation when reversals or FX are ambiguous.
            }
            $evidence = ['total_cents' => $total, 'paid_cents' => $paid, 'linked_count' => $valid];
            if ($status === 'draft' && $valid > 0) {
                $findings[] = $this->finding(...$args, case: 'draft_with_payment',
                    classification: Occurrence::SUSPECTED, severity: 'warning', evidence: $evidence);
            } elseif (in_array($status, ['cancelled', 'canceled', 'void'], true) && $valid > 0) {
                $findings[] = $this->finding(...$args, case: 'cancelled_with_payment',
                    classification: Occurrence::SUSPECTED, severity: 'warning', evidence: $evidence);
            } elseif ($status === 'paid' && $total > 0 && $paid === 0) {
                $findings[] = $this->finding(...$args, case: 'paid_without_payment',
                    classification: Occurrence::SUSPECTED, severity: 'warning', evidence: $evidence);
            } elseif ($status === 'paid' && $paid < $total) {
                $findings[] = $this->finding(...$args, case: 'paid_shortfall',
                    classification: Occurrence::SUSPECTED, severity: 'warning', evidence: $evidence);
            } elseif ($valid > 0 && $paid > $total) {
                $findings[] = $this->finding(...$args, case: 'payment_exceeds_document',
                    classification: Occurrence::SUSPECTED, severity: 'warning', evidence: $evidence);
            }
            // Partial payments and unlinked transactions are not errors.
        }
        return $findings;
    }

    /** @param array<string,int|bool|string> $evidence */
    private function finding(
        int $companyId,
        int $id,
        ?string $competence,
        string $case,
        string $classification,
        string $severity,
        array $evidence,
    ): Occurrence {
        return new Occurrence(
            $companyId, $this->id(), $this->version(), $classification, $severity,
            'document', (string) $id, $case, $competence, $evidence,
            'audit.' . $case, 'audit.review_source',
        );
    }
}
