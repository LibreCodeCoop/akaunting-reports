<?php
// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Modules\AkauntingReports\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Read-only ledger using the Akaunting database as the financial source of truth.
 * Does not create, repair or modify accounting entries.
 */
final class CapitalSocialLedger
{
    /** @return list<array<string,mixed>> */
    public function rows(CapitalSocialPolicy $policy, ?int $contactId = null, ?string $asOf = null): array
    {
        if ($contactId !== null && $contactId <= 0) {
            throw new InvalidArgumentException('Invalid member identifier.');
        }
        if ($asOf !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $asOf)
            || !checkdate((int) substr($asOf, 5, 2), (int) substr($asOf, 8, 2), (int) substr($asOf, 0, 4)))) {
            throw new InvalidArgumentException('Invalid as-of date. Use YYYY-MM-DD.');
        }

        $transactions = DB::table('transactions as t')
            ->join('contacts as c', static function ($join): void {
                $join->on('c.id', '=', 't.contact_id')->on('c.company_id', '=', 't.company_id');
            })
            ->leftJoin('categories as cat', static function ($join): void {
                $join->on('cat.id', '=', 't.category_id')->on('cat.company_id', '=', 't.company_id');
            })
            ->where('t.company_id', $policy->companyId)
            ->whereNull('t.deleted_at')->whereNull('t.document_id')
            ->whereNotNull('t.contact_id')
            ->where(static function ($query) use ($policy): void {
                $query->where('t.category_id', $policy->categoryId)
                    ->orWhere('cat.code', $policy->refundCategoryCode)
                    ->orWhereRaw('LOWER(t.description) LIKE ?', ['%capital social%'])
                    ->orWhereRaw("REPLACE(LOWER(t.description), ' ', '') LIKE ?", ['%capitalsocial%']);
            })
            ->select('t.id','t.contact_id','c.name','c.tax_number','t.amount','t.description',
                't.currency_code','t.paid_at','t.type','t.category_id','cat.code as category_code');
        if ($asOf !== null) {
            $transactions->where('t.paid_at', '<=', $asOf . ' 23:59:59');
        }
        if ($contactId !== null) {
            $transactions->where('t.contact_id', $contactId);
        }

        $documents = DB::table('documents as d')
            ->join('contacts as c', static function ($join): void {
                $join->on('c.id', '=', 'd.contact_id')->on('c.company_id', '=', 'd.company_id');
            })
            ->where('d.company_id', $policy->companyId)
            ->whereNull('d.deleted_at')->whereNotNull('d.contact_id')
            ->where(static function ($query) use ($policy): void {
                $query->where('d.category_id', $policy->categoryId)
                    ->orWhereExists(static function ($items) use ($policy): void {
                        $items->selectRaw('1')->from('document_items as ci')
                            ->whereColumn('ci.document_id', 'd.id')
                            ->where('ci.item_id', $policy->itemId)
                            ->whereNull('ci.deleted_at');
                    });
            })
            ->select('d.id','d.contact_id','c.name','c.tax_number','d.type','d.status',
                'd.category_id','d.currency_code','d.amount','d.due_at','d.issued_at');
        if ($asOf !== null) {
            $documents->where('d.issued_at', '<=', $asOf . ' 23:59:59');
        }
        if ($contactId !== null) {
            $documents->where('d.contact_id', $contactId);
        }

        $entries = $transactions->get();
        $invoices = $documents->get();
        $documentsById = [];
        foreach ($invoices as $document) {
            $documentsById[(int) $document->id] = $document;
        }

        $lineItems = [];
        $payments = [];
        // Chunk IDs to avoid unsafe giant IN clauses and excessive memory in driver bindings.
        foreach (array_chunk(array_keys($documentsById), 250) as $chunk) {
            foreach (DB::table('document_items')
                ->whereIn('document_id', $chunk)->whereNull('deleted_at')
                ->select('document_id', 'item_id', 'total')->get() as $item) {
                $lineItems[(int) $item->document_id][] = $item;
            }
            $paymentQuery = DB::table('transactions')
                ->where('company_id', $policy->companyId)->whereIn('document_id', $chunk)
                ->whereNull('deleted_at')->select('document_id', 'type', 'category_id',
                    'currency_code', 'amount', 'paid_at');
            if ($asOf !== null) {
                $paymentQuery->where('paid_at', '<=', $asOf . ' 23:59:59');
            }
            foreach ($paymentQuery->get() as $payment) {
                $payments[(int) $payment->document_id][] = $payment;
            }
        }

        $identities = [];
        foreach ($entries as $entry) {
            $key = self::identityKey((string) $entry->tax_number, (int) $entry->contact_id);
            $identities[$key] = min($identities[$key] ?? (int) $entry->contact_id, (int) $entry->contact_id);
        }
        foreach ($invoices as $document) {
            $key = self::identityKey((string) $document->tax_number, (int) $document->contact_id);
            $identities[$key] = min($identities[$key] ?? (int) $document->contact_id, (int) $document->contact_id);
        }

        $rows = [];
        foreach ($entries as $entry) {
            $key = self::identityKey((string) $entry->tax_number, (int) $entry->contact_id);
            $evaluated = CapitalSocialRules::transaction((array) $entry, $policy);
            $rows[] = self::movement('transaction', (int) $entry->id, $identities[$key], $key,
                (string) $entry->name, (string) $entry->paid_at, $evaluated,
                (string) $entry->currency_code);
        }
        foreach ($invoices as $document) {
            $id = (int) $document->id;
            $key = self::identityKey((string) $document->tax_number, (int) $document->contact_id);
            $items = $lineItems[$id] ?? [];
            $capital = 0;
            $capitalLines = 0;
            $advanceDeducted = 0;
            foreach ($items as $item) {
                $cents = Money::cents((string) $item->total);
                if ((int) $item->item_id === $policy->itemId) {
                    ++$capitalLines;
                    $capital += $cents;
                }
                if ($policy->advanceItemId !== null && (int) $item->item_id === $policy->advanceItemId && $cents < 0) {
                    $advanceDeducted -= $cents;
                }
            }
            if ($capitalLines === 0 && (int) $document->category_id === $policy->categoryId) {
                $capital = Money::cents((string) $document->amount);
            }
            $total = Money::cents((string) $document->amount);
            $paid = 0;
            $advancePaid = 0;
            $lastPaidAt = null;
            $expectedType = $document->type === 'bill' ? 'expense' : 'income';
            foreach ($payments[$id] ?? [] as $payment) {
                if ((string) $payment->type !== $expectedType || (string) $payment->currency_code !== (string) $document->currency_code) {
                    continue;
                }
                $amount = Money::cents((string) $payment->amount);
                $paid += $amount;
                if ($policy->advanceCategoryId !== null && $expectedType === 'expense'
                    && (int) $payment->category_id === $policy->advanceCategoryId) {
                    $advancePaid += $amount;
                }
                if ($lastPaidAt === null || (string) $payment->paid_at > $lastPaidAt) {
                    $lastPaidAt = (string) $payment->paid_at;
                }
            }
            if ($document->type === 'bill' && $document->status === 'paid' && $advancePaid > 0
                && $advancePaid === $advanceDeducted && $paid - $advancePaid === $total) {
                $paid -= $advancePaid;
            }
            $zeroNetted = $document->type === 'bill' && $document->status === 'paid'
                && $total === 0 && $paid === 0 && $capital < 0
                && $capitalLines > 0 && $lastPaidAt !== null;
            $hasPayment = ($paid > 0 && $lastPaidAt !== null) || $zeroNetted;
            $evaluated = CapitalSocialRules::document([
                'document_amount_cents' => $total,
                'paid_cents' => $paid,
                'capital_cents' => $capital,
                'capital_lines' => $capitalLines,
                'item_lines' => count($items),
                'has_payment' => $hasPayment,
                'type' => (string) $document->type,
                'status' => (string) $document->status,
                'currency_code' => (string) $document->currency_code,
            ]);
            $rows[] = self::movement('document', $id, $identities[$key], $key,
                (string) $document->name, $lastPaidAt ?? (string) $document->due_at,
                $evaluated, (string) $document->currency_code);
        }
        usort($rows, static fn(array $a, array $b): int => [$a['name'], $a['date'], $a['source'], $a['id']]
            <=> [$b['name'], $b['date'], $b['source'], $b['id']]);
        return $rows;
    }

    /** @param array{kind:string,amount_cents:int,reason:string} $evaluated
     * @return array<string,mixed>
     */
    private static function movement(string $source, int $id, int $contactId, string $identity,
        string $name, string $date, array $evaluated, string $currency): array
    {
        return [
            'source' => $source,
            'id' => $id,
            'contact_id' => $contactId,
            'identity' => $identity,
            'name' => $name,
            'date' => substr($date, 0, 10),
            'kind' => $evaluated['kind'],
            'amount_cents' => $evaluated['amount_cents'],
            'status' => $evaluated['reason'] === '' ? 'confirmado' : 'revisar',
            'reason' => $evaluated['reason'],
            'currency' => $currency,
        ];
    }

    private static function identityKey(string $taxNumber, int $contactId): string
    {
        $digits = preg_replace('/\D+/', '', $taxNumber) ?? '';
        return in_array(strlen($digits), [11, 14], true) && !preg_match('/^(\d)\1+$/D', $digits)
            ? 'tax:' . $digits : 'contact:' . $contactId;
    }
}
